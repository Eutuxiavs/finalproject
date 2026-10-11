<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Offer;
use App\Repository\ItemRepository;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MarketplaceController extends AbstractController
{
    #[Route('/marketplace', name: 'marketplace')]
    public function index(Request $request, ItemRepository $items): Response
    {
        $filter = (string) $request->query->get('filter', 'all');
        if (!in_array($filter, ['all', 'sale', 'lost'], true)) {
            $filter = 'all';
        }

        $q = trim((string) $request->query->get('q', ''));
        $category = (string) $request->query->get('category', '');
        if (!in_array($category, Item::CATEGORIES, true)) {
            $category = '';
        }

        return $this->render('app/marketplace.html.twig', [
            'items' => $items->findForMarketplace($filter, $q, $category),
            'filter' => $filter,
            'q' => $q,
            'category' => $category,
            'categories' => Item::CATEGORIES,
        ]);
    }

    #[Route('/marketplace/{id}/offer', name: 'offer_make', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function makeOffer(Item $item, Request $request, EntityManagerInterface $em, OfferRepository $offers): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('offer_item_' . $item->id, (string) $request->request->get('_token'))) {
            return $this->back('marketplace', 'error', 'Your session expired. Please try again.');
        }
        if ($item->status !== Item::STATUS_FOR_SALE) {
            return $this->back('marketplace', 'error', 'This item is no longer for sale.');
        }
        if ($item->owner->getId() === $user->getId()) {
            return $this->back('marketplace', 'error', 'You cannot make an offer on your own item.');
        }

        $amount = str_replace(',', '', trim((string) $request->request->get('amount')));
        if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 99999999) {
            return $this->back('marketplace', 'error', 'Please enter a valid offer amount.');
        }

        if ($offers->findOneBy(['item' => $item, 'buyer' => $user, 'status' => Offer::PENDING])) {
            return $this->back('offers', 'error', 'You already have a pending offer on this item.');
        }

        $offer = new Offer();
        $offer->item = $item;
        $offer->buyer = $user;
        $offer->seller = $item->owner;
        $offer->amount = number_format((float) $amount, 2, '.', '');
        $offer->message = mb_substr(trim((string) $request->request->get('message')), 0, 500) ?: null;

        $em->persist($offer);
        $em->flush();

        return $this->back('offers', 'success', 'Offer sent. The seller will see it in their Offers page.');
    }

    #[Route('/offers', name: 'offers')]
    public function offers(OfferRepository $offers): Response
    {
        $user = $this->getUser();

        return $this->render('app/offers.html.twig', [
            'received' => $offers->findBy(['seller' => $user], ['id' => 'DESC']),
            'made' => $offers->findBy(['buyer' => $user], ['id' => 'DESC']),
        ]);
    }

    #[Route(
        '/offers/{id}/{action}',
        name: 'offer_action',
        methods: ['POST'],
        requirements: ['id' => '\d+', 'action' => 'accept|decline|cancel|confirm'],
    )]
    public function action(Offer $offer, string $action, Request $request, EntityManagerInterface $em, OfferRepository $offers): Response
    {
        $user = $this->getUser();
        $isSeller = $offer->seller->getId() === $user->getId();
        $isBuyer = $offer->buyer->getId() === $user->getId();

        if (!$isSeller && !$isBuyer) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('offer_' . $offer->id, (string) $request->request->get('_token'))) {
            return $this->back('offers', 'error', 'Your session expired. Please try again.');
        }

        $item = $offer->item;
        $open = [Offer::PENDING, Offer::ACCEPTED];

        switch ($action) {
            case 'accept':
                if (!$isSeller
                    || $offer->status !== Offer::PENDING
                    || $item->status !== Item::STATUS_FOR_SALE
                    || $item->owner->getId() !== $offer->seller->getId()) {
                    return $this->back('offers', 'error', 'This offer can no longer be accepted.');
                }
                $offer->status = Offer::ACCEPTED;
                foreach ($offers->findBy(['item' => $item, 'status' => Offer::PENDING]) as $other) {
                    if ($other !== $offer) {
                        $other->status = Offer::DECLINED;
                    }
                }
                $message = 'Offer accepted. Ownership moves only after both of you confirm the handover.';
                break;

            case 'decline':
                if (!$isSeller || $offer->status !== Offer::PENDING) {
                    return $this->back('offers', 'error', 'This offer cannot be declined.');
                }
                $offer->status = Offer::DECLINED;
                $message = 'Offer declined.';
                break;

            case 'cancel':
                $allowed = ($isBuyer && in_array($offer->status, $open, true))
                    || ($isSeller && $offer->status === Offer::ACCEPTED);
                if (!$allowed) {
                    return $this->back('offers', 'error', 'This offer cannot be cancelled.');
                }
                $offer->status = Offer::CANCELLED;
                $message = 'Offer cancelled.';
                break;

            case 'confirm':
                if ($offer->status !== Offer::ACCEPTED) {
                    return $this->back('offers', 'error', 'This offer is not waiting for a handover.');
                }
                if ($item->owner->getId() !== $offer->seller->getId()) {
                    $offer->status = Offer::CANCELLED;
                    $em->flush();

                    return $this->back('offers', 'error', 'The seller no longer owns this item, so the offer was cancelled.');
                }

                if ($isSeller) {
                    $offer->sellerConfirmed = true;
                }
                if ($isBuyer) {
                    $offer->buyerConfirmed = true;
                }

                if ($offer->sellerConfirmed && $offer->buyerConfirmed) {
                    $item->owner = $offer->buyer;
                    $item->status = Item::STATUS_OWNED;
                    $item->price = null;
                    $offer->status = Offer::COMPLETED;

                    foreach ($offers->findBy(['item' => $item, 'status' => $open]) as $other) {
                        if ($other !== $offer) {
                            $other->status = Offer::CANCELLED;
                        }
                    }
                    $message = 'Handover complete. Ownership has been transferred.';
                } else {
                    $message = 'Your confirmation was saved. Waiting for the other side.';
                }
                break;

            default:
                return $this->back('offers', 'error', 'Unknown action.');
        }

        $em->flush();

        return $this->back('offers', 'success', $message);
    }

    private function back(string $route, string $type, string $message): Response
    {
        $this->addFlash($type, $message);

        return $this->redirectToRoute($route);
    }
}