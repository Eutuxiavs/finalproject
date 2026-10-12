<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\OfferRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistryController extends AbstractController
{
    #[Route('/registry', name: 'user_registry')]
    public function index(ItemRepository $items): Response
    {
        $mine = $items->findBy(['owner' => $this->getUser()], ['id' => 'DESC']);

        $stats = [
            'filed' => count($mine),
            'sale' => count(array_filter($mine, fn (Item $i) => $i->status === Item::STATUS_FOR_SALE)),
            'lost' => count(array_filter($mine, fn (Item $i) => $i->status === Item::STATUS_LOST)),
        ];

        return $this->render('app/registry.html.twig', [
            'items' => $mine,
            'stats' => $stats,
        ]);
    }

    #[Route('/registry/new', name: 'item_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImageUploader $uploader): Response
    {
        $errors = [];
        $data = [
            'name' => '', 'category' => '', 'serial' => '', 'condition' => '',
            'city' => '', 'notes' => '', 'listing' => 'inventory', 'price' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('item_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }

            foreach (array_keys($data) as $key) {
                $data[$key] = trim((string) $request->request->get($key, $data[$key]));
            }

            if ($data['name'] === '' || mb_strlen($data['name']) > 160) {
                $errors[] = 'Please enter an item name (up to 160 characters).';
            }
            if (!in_array($data['category'], Item::CATEGORIES, true)) {
                $errors[] = 'Please choose a category.';
            }
            if ($data['condition'] !== '' && !in_array($data['condition'], Item::CONDITIONS, true)) {
                $errors[] = 'Please choose a valid condition.';
            }
            if (mb_strlen($data['notes']) > 1000) {
                $errors[] = 'Notes can be up to 1000 characters.';
            }
            if (!in_array($data['listing'], ['inventory', 'sale', 'lost'], true)) {
                $errors[] = 'Please choose what should happen to this item.';
            }

            $price = null;
            if ($data['listing'] === 'sale') {
                $clean = str_replace(',', '', $data['price']);
                if (!is_numeric($clean) || (float) $clean <= 0 || (float) $clean > 99999999) {
                    $errors[] = 'Please enter a valid asking price.';
                } else {
                    $price = number_format((float) $clean, 2, '.', '');
                }
            }

            $file = $request->files->get('photo');
            if ($file && ($photoError = $uploader->validate($file))) {
                $errors[] = $photoError;
            }

            if (!$errors) {
                $item = new Item();
                $item->name = $data['name'];
                $item->category = $data['category'];
                $item->serialNumber = $data['serial'] ?: null;
                $item->itemCondition = $data['condition'] ?: null;
                $item->city = $data['city'] ?: null;
                $item->notes = $data['notes'] ?: null;
                $item->owner = $this->getUser();

                if ($data['listing'] === 'sale') {
                    $item->status = Item::STATUS_FOR_SALE;
                    $item->price = $price;
                } elseif ($data['listing'] === 'lost') {
                    $item->status = Item::STATUS_LOST;
                }

                if ($file) {
                    $item->photo = $uploader->save($file, 'items');
                }

                $em->persist($item);
                $em->flush();

                $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $item->category), 0, 4));
                $item->claimId = sprintf('YZ-%s-%06d', $letters, $item->id);
                $em->flush();

                $message = match ($item->status) {
                    Item::STATUS_FOR_SALE => 'Claim ' . $item->claimId . ' filed and listed on the marketplace.',
                    Item::STATUS_LOST => 'Claim ' . $item->claimId . ' filed and posted to Lost & Found.',
                    default => 'Claim ' . $item->claimId . ' filed and kept in your inventory.',
                };
                $this->addFlash('success', $message);

                return $this->redirectToRoute('user_registry');
            }
        }

        return $this->render('app/item_new.html.twig', [
            'errors' => $errors,
            'data' => $data,
            'categories' => Item::CATEGORIES,
            'conditions' => Item::CONDITIONS,
        ]);
    }

    #[Route('/registry/{id}', name: 'item_show', requirements: ['id' => '\d+'])]
    public function show(Item $item, OfferRepository $offers): Response
    {
        $this->assertOwner($item);

        return $this->render('app/item_show.html.twig', [
            'item' => $item,
            'offers' => $offers->findBy(
                ['item' => $item, 'status' => [Offer::PENDING, Offer::ACCEPTED]],
                ['id' => 'DESC'],
            ),
        ]);
    }

    #[Route('/registry/{id}/update', name: 'item_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Item $item, Request $request, EntityManagerInterface $em, OfferRepository $offers): Response
    {
        $this->assertOwner($item);

        if (!$this->isCsrfTokenValid('item_' . $item->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->backTo($item, $request);
        }

        $action = (string) $request->request->get('action');

        switch ($action) {
            case 'sell':
                $price = str_replace(',', '', trim((string) $request->request->get('price')));
                if (!is_numeric($price) || (float) $price <= 0 || (float) $price > 99999999) {
                    $this->addFlash('error', 'Please enter a valid price.');

                    return $this->backTo($item, $request);
                }
                $item->status = Item::STATUS_FOR_SALE;
                $item->price = number_format((float) $price, 2, '.', '');
                $this->addFlash('success', 'Your item is now listed on the marketplace.');
                break;

            case 'unlist':
                $this->closeOffers($item, $offers);
                $item->status = Item::STATUS_OWNED;
                $item->price = null;
                $this->addFlash('success', 'Your item is back in your inventory.');
                break;

            case 'lost':
                $this->closeOffers($item, $offers);
                $item->status = Item::STATUS_LOST;
                $item->price = null;
                $this->addFlash('success', 'Reported lost. It now shows in Lost & Found.');
                break;

            case 'found':
                $item->status = Item::STATUS_OWNED;
                $this->addFlash('success', 'Glad you found it. The status is back to owned.');
                break;

            case 'delete':
                $em->remove($item);
                $em->flush();
                $this->addFlash('success', 'The claim was deleted.');

                return $this->redirectToRoute('user_registry');

            default:
                $this->addFlash('error', 'Unknown action.');

                return $this->backTo($item, $request);
        }

        $em->flush();

        return $this->backTo($item, $request);
    }

    private function backTo(Item $item, Request $request): Response
    {
        if ($request->request->get('back') === 'registry') {
            return $this->redirectToRoute('user_registry');
        }

        return $this->redirectToRoute('item_show', ['id' => $item->id]);
    }

    private function assertOwner(Item $item): void
    {
        $user = $this->getUser();

        if (!$user instanceof User || $item->owner?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function closeOffers(Item $item, OfferRepository $offers): void
    {
        foreach ($offers->findBy(['item' => $item, 'status' => [Offer::PENDING, Offer::ACCEPTED]]) as $offer) {
            $offer->status = Offer::CANCELLED;
        }
    }
}