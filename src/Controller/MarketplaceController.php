<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\OfferRepository;
use App\Repository\ReviewRepository;
use App\Service\OfferService;
use App\Service\TrustService;
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
        $category = (string) $request->query->get('category', '');
        if (!in_array($category, Item::CATEGORIES, true)) {
            $category = '';
        }
        $q = mb_substr(trim((string) $request->query->get('q', '')), 0, 80);

        $result = $items->searchMarketplace($filter, $q, $category, $request->query->getInt('page', 1));

        return $this->render('app/marketplace.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'filter' => $filter,
            'category' => $category,
            'q' => $q,
            'categories' => Item::CATEGORIES,
        ]);
    }

    #[Route('/marketplace/{id}', name: 'listing_show', requirements: ['id' => '\d+'])]
    public function listing(Item $item, TrustService $trust, OfferRepository $offers): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isOwner = $item->owner?->getId() === $user->getId();

        if (!$item->isPublic() && !$isOwner) {
            throw $this->createNotFoundException();
        }

        return $this->render('app/listing_show.html.twig', [
            'item' => $item,
            'isOwner' => $isOwner,
            'blocked' => !$isOwner && $trust->isBlocked($user, $item->owner),
            'verified' => $trust->isVerified($item->owner),
            'rating' => $trust->rating($item->owner),
            'pending' => $isOwner ? null : $offers->findPendingFor($item, $user),
        ]);
    }

    #[Route('/marketplace/{id}/offer', name: 'offer_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newOffer(Item $item, Request $request, OfferService $offerService, ItemRepository $items, TrustService $trust, OfferRepository $offers): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($item->status !== Item::STATUS_FOR_SALE) {
            $this->addFlash('error', 'That listing is no longer available for offers.');

            return $this->redirectToRoute('marketplace');
        }
        if ($item->owner?->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot make an offer on your own item.');

            return $this->redirectToRoute('marketplace');
        }
        if ($trust->isBlocked($user, $item->owner)) {
            $this->addFlash('error', 'You cannot make an offer to this user.');

            return $this->redirectToRoute('marketplace');
        }
        if ($offers->findPendingFor($item, $user)) {
            $this->addFlash('success', 'You already have a pending offer on this item.');

            return $this->redirectToRoute('offers');
        }

        $tradeable = $items->findTradeable($user, $item);
        $errors = [];
        $data = ['amount' => $item->price, 'trade' => '', 'message' => '', 'mode' => 'cash'];

        if ($request->isMethod('POST')) {
            $data['mode'] = (string) $request->request->get('mode', 'cash');
            $data['amount'] = str_replace(',', '', trim((string) $request->request->get('amount', '0')));
            $data['trade'] = (string) $request->request->get('trade', '');
            $data['message'] = trim((string) $request->request->get('message', ''));

            if (!$this->isCsrfTokenValid('offer_' . $item->id, (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if ($data['amount'] === '') {
                $data['amount'] = '0';
            }
            if (!is_numeric($data['amount']) || (float) $data['amount'] < 0) {
                $errors[] = 'Enter a valid cash amount.';
            }
            if (mb_strlen($data['message']) > 500) {
                $errors[] = 'Your message can be up to 500 characters.';
            }

            $trade = null;
            if ($data['mode'] !== 'cash' && $data['trade'] !== '') {
                foreach ($tradeable as $candidate) {
                    if ((string) $candidate->id === $data['trade']) {
                        $trade = $candidate;
                    }
                }
                if (!$trade) {
                    $errors[] = 'Choose one of your own available items to trade.';
                }
            }
            if ($data['mode'] === 'trade') {
                $data['amount'] = '0';
            }

            if (!$errors) {
                try {
                    $offerService->create($item, $user, $data['amount'], $trade, $data['message']);
                    $this->addFlash('success', sprintf('Offer sent to %s. It expires in %d days if they do not respond.', $item->owner->getFullName(), Offer::VALID_DAYS));

                    return $this->redirectToRoute('offers');
                } catch (\DomainException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        return $this->render('app/offer_new.html.twig', [
            'item' => $item,
            'tradeable' => $tradeable,
            'errors' => $errors,
            'data' => $data,
        ]);
    }

    #[Route('/offers', name: 'offers')]
    public function offers(OfferRepository $offers, ReviewRepository $reviews): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $offers->expireDue();

        $made = $offers->findBy(['buyer' => $user], ['id' => 'DESC']);
        $received = $offers->findBy(['seller' => $user], ['id' => 'DESC']);

        $reviewed = [];
        foreach ($reviews->findBy(['reviewer' => $user]) as $review) {
            $reviewed[$review->offer->id] = true;
        }

        return $this->render('app/offers.html.twig', [
            'made' => $made,
            'received' => $received,
            'reviewed' => $reviewed,
        ]);
    }

    #[Route('/offers/{id}/{action}', name: 'offer_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'accept|decline|withdraw|cancel|confirm|dispute'])]
    public function act(Offer $offer, string $action, Request $request, OfferService $offerService, OfferRepository $offers): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('offer_' . $offer->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('offers');
        }

        $offers->expireDue();

        try {
            $this->addFlash('success', $offerService->apply($offer, $user, $action));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('offers');
    }

    #[Route('/offers/{id}/meetup', name: 'offer_meetup', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function meetup(Offer $offer, Request $request, OfferService $offerService): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('offer_' . $offer->id, (string) $request->request->get('_token'))) {
            try {
                $offerService->setMeetupNote($offer, $user, (string) $request->request->get('note', ''));
                $this->addFlash('success', 'Meetup note saved.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('offers');
    }

    #[Route('/offers/{id}/review', name: 'offer_review', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function review(Offer $offer, Request $request, EntityManagerInterface $em, ReviewRepository $reviews): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('review_' . $offer->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('offers');
        }
        if ($offer->status !== Offer::COMPLETED || !$offer->involves($user)) {
            $this->addFlash('error', 'You can only review a completed deal you took part in.');

            return $this->redirectToRoute('offers');
        }

        $rating = $request->request->getInt('rating');
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Choose a rating from 1 to 5 stars.');

            return $this->redirectToRoute('offers');
        }
        if ($reviews->findOneBy(['offer' => $offer, 'reviewer' => $user])) {
            $this->addFlash('error', 'You already reviewed this deal.');

            return $this->redirectToRoute('offers');
        }

        $comment = trim((string) $request->request->get('comment', ''));
        $review = new Review();
        $review->offer = $offer;
        $review->reviewer = $user;
        $review->reviewee = $offer->seller->getId() === $user->getId() ? $offer->buyer : $offer->seller;
        $review->rating = $rating;
        $review->comment = $comment === '' ? null : mb_substr($comment, 0, 500);
        $em->persist($review);
        $em->flush();

        $this->addFlash('success', 'Thanks for the review.');

        return $this->redirectToRoute('offers');
    }
}
