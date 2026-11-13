<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\OwnershipHistory;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Entity\PlatformRevenue;
use App\Repository\OfferRepository;
use App\Service\OfferService;
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
            'sale' => count(array_filter($mine, fn (Item $i) => in_array($i->status, [Item::STATUS_FOR_SALE, Item::STATUS_RESERVED], true))),
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
    public function show(Item $item, OfferRepository $offers, EntityManagerInterface $em): Response
    {
        $this->assertOwner($item);

        return $this->render('app/item_show.html.twig', [
            'item' => $item,
            'offers' => $offers->findOpenForItem($item),
            'history' => $em->getRepository(OwnershipHistory::class)->findBy(['item' => $item], ['id' => 'DESC']),
            'featureFee' => PlatformRevenue::FEATURED_FEE,
            'featureDays' => PlatformRevenue::FEATURED_DAYS,
        ]);
    }

    #[Route('/registry/{id}/update', name: 'item_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Item $item, Request $request, EntityManagerInterface $em, OfferService $offerService, ImageUploader $uploader): Response
    {
        $this->assertOwner($item);

        if (!$this->isCsrfTokenValid('item_' . $item->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->backTo($item, $request);
        }

        $action = (string) $request->request->get('action');

        // An item locked into an accepted deal must be released first.
        if ($item->status === Item::STATUS_RESERVED && in_array($action, ['sell', 'unlist', 'lost', 'delete', 'feature', 'photo'], true)) {
            $this->addFlash('error', 'This item is part of an accepted deal. Finish or cancel the deal on the Offers page first.');

            return $this->backTo($item, $request);
        }

        switch ($action) {
            case 'photo':
                $file = $request->files->get('photo');
                if (!$file) {
                    $this->addFlash('error', 'Choose a photo to upload.');

                    return $this->backTo($item, $request);
                }
                if ($error = $uploader->validate($file)) {
                    $this->addFlash('error', $error);

                    return $this->backTo($item, $request);
                }
                $uploader->delete($item->photo);
                $item->photo = $uploader->save($file, 'items');
                $this->addFlash('success', 'Photo updated.');
                break;

            case 'sell':
                if ($item->status !== Item::STATUS_OWNED) {
                    $this->addFlash('error', 'Only items in your inventory can be listed for sale.');

                    return $this->backTo($item, $request);
                }
                if (!$item->photo) {
                    $this->addFlash('error', 'Add a real photo of the item before listing it. Buyers need to see it.');

                    return $this->backTo($item, $request);
                }
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
                $offerService->cancelOpenOffersFor($item);
                $item->status = Item::STATUS_OWNED;
                $item->price = null;
                $item->isFeatured = false;
                $item->featuredUntil = null;
                $this->addFlash('success', 'Your item is back in your inventory.');
                break;

            case 'lost':
                $offerService->cancelOpenOffersFor($item);
                $item->status = Item::STATUS_LOST;
                $item->price = null;
                $item->isFeatured = false;
                $item->featuredUntil = null;
                $this->addFlash('success', 'Reported lost. It now shows in Lost & Found.');
                break;

            case 'found':
                if ($item->status !== Item::STATUS_LOST) {
                    $this->addFlash('error', 'Only items reported lost can be marked found.');

                    return $this->backTo($item, $request);
                }
                $item->status = Item::STATUS_OWNED;
                $this->addFlash('success', 'Glad you found it. The status is back to owned.');
                break;

            case 'feature':
                if ($item->status !== Item::STATUS_FOR_SALE) {
                    $this->addFlash('error', 'Only items listed for sale can be featured.');

                    return $this->backTo($item, $request);
                }
                if ($item->isFeaturedNow()) {
                    $this->addFlash('error', 'This listing is already featured.');

                    return $this->backTo($item, $request);
                }
                $item->isFeatured = true;
                $item->featuredUntil = new \DateTimeImmutable('+' . PlatformRevenue::FEATURED_DAYS . ' days');
                $fee = new PlatformRevenue();
                $fee->source = PlatformRevenue::SOURCE_FEATURED;
                $fee->amount = PlatformRevenue::FEATURED_FEE;
                $fee->user = $this->getUser();
                $fee->item = $item;
                $em->persist($fee);
                $this->addFlash('success', sprintf('Featured for %d days. This is a simulated $%s charge; no real payment was taken.', PlatformRevenue::FEATURED_DAYS, PlatformRevenue::FEATURED_FEE));
                break;

            case 'delete':
                $offerService->cancelOpenOffersFor($item);
                $uploader->delete($item->photo);
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
}
