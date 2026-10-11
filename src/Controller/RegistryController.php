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
        $data = ['name' => '', 'category' => '', 'serial' => '', 'condition' => '', 'city' => '', 'notes' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('item_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }

            foreach (array_keys($data) as $key) {
                $data[$key] = trim((string) $request->request->get($key));
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

                if ($file) {
                    $item->photo = $uploader->save($file, 'items');
                }

                $em->persist($item);
                $em->flush();

                $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $item->category), 0, 4));
                $item->claimId = sprintf('YZ-%s-%06d', $letters, $item->id);
                $em->flush();

                $this->addFlash('success', 'Claim filed. Your claim ID is ' . $item->claimId . '.');

                return $this->redirectToRoute('item_show', ['id' => $item->id]);
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

            return $this->redirectToRoute('item_show', ['id' => $item->id]);
        }

        $action = (string) $request->request->get('action');

        switch ($action) {
            case 'sell':
                $price = str_replace(',', '', trim((string) $request->request->get('price')));
                if (!is_numeric($price) || (float) $price <= 0 || (float) $price > 99999999) {
                    $this->addFlash('error', 'Please enter a valid price.');

                    return $this->redirectToRoute('item_show', ['id' => $item->id]);
                }
                $item->status = Item::STATUS_FOR_SALE;
                $item->price = number_format((float) $price, 2, '.', '');
                $this->addFlash('success', 'Your item is now listed on the marketplace.');
                break;

            case 'unlist':
                $this->closeOffers($item, $offers);
                $item->status = Item::STATUS_OWNED;
                $item->price = null;
                $this->addFlash('success', 'Your item was taken off the marketplace.');
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

                return $this->redirectToRoute('item_show', ['id' => $item->id]);
        }

        $em->flush();

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