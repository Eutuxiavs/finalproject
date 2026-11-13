public function create(<?php

namespace App\Service;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\OwnershipHistory;
use App\Entity\PlatformRevenue;
use App\Entity\User;
use App\Repository\OfferRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Every offer state change lives here so the rules are in one place.
 * Each change runs in a transaction with a row lock, so two people clicking at
 * the same moment cannot accept two offers or transfer an item twice.
 *
 * Methods throw \DomainException with a user-friendly message when a rule is broken.
 */
class OfferService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OfferRepository $offers,
        private TrustService $trust,
    ) {
    }
    /** A broken rule only rolls back; it must not close the EntityManager. */
    private function transactional(callable $work): mixed
    {
        $this->em->beginTransaction();

        try {
            $result = $work();
            $this->em->flush();
            $this->em->commit();

            return $result;
        } catch (\DomainException $e) {
            $this->em->rollback();

            throw $e;
        } catch (\Throwable $e) {
            $this->em->close();
            $this->em->rollback();

            throw $e;
        }
    }
    public function create(Item $item, User $buyer, string $amount, ?Item $tradeItem, ?string $message): Offer
    {
        return $this->em->wrapInTransaction(function () use ($item, $buyer, $amount, $tradeItem, $message): Offer {
            $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);

            if ($item->status !== Item::STATUS_FOR_SALE) {
                throw new \DomainException('This item is no longer available for offers.');
            }
            if ($item->owner?->getId() === $buyer->getId()) {
                throw new \DomainException('You cannot make an offer on your own item.');
            }
            if ($this->trust->isBlocked($buyer, $item->owner)) {
                throw new \DomainException('You cannot make an offer to this user.');
            }
            if ($this->offers->findPendingFor($item, $buyer)) {
                throw new \DomainException('You already have a pending offer on this item.');
            }

            $cash = (float) $amount;
            if ($cash < 0 || $cash > 99999999) {
                throw new \DomainException('Please enter a valid cash amount.');
            }
            if ($tradeItem !== null && ($tradeItem->owner?->getId() !== $buyer->getId() || $tradeItem->status !== Item::STATUS_OWNED)) {
                throw new \DomainException('Choose one of your own available items to trade.');
            }
            if ($cash <= 0 && $tradeItem === null) {
                throw new \DomainException('Offer a cash amount, an item to trade, or both.');
            }

            $offer = new Offer();
            $offer->item = $item;
            $offer->buyer = $buyer;
            $offer->seller = $item->owner;
            $offer->amount = number_format($cash, 2, '.', '');
            $offer->tradeItem = $tradeItem;
            $offer->type = $tradeItem === null ? Offer::TYPE_CASH : ($cash > 0 ? Offer::TYPE_TRADE_CASH : Offer::TYPE_TRADE);
            $offer->message = $message !== null && $message !== '' ? mb_substr($message, 0, 500) : null;

            $this->em->persist($offer);

            return $offer;
        });
    }

    /** Applies accept / decline / withdraw / cancel / confirm / dispute and returns a success message. */
    public function apply(Offer $offer, User $actor, string $action): string
    {
        return $this->em->wrapInTransaction(function () use ($offer, $actor, $action): string {
            $this->em->refresh($offer, LockMode::PESSIMISTIC_WRITE);

            if (!$offer->involves($actor)) {
                throw new \DomainException('You are not part of this offer.');
            }

            $isSeller = $offer->seller->getId() === $actor->getId();
            $isBuyer = $offer->buyer->getId() === $actor->getId();

            if ($offer->status === Offer::PENDING && $offer->expiresAt !== null && $offer->expiresAt < new \DateTimeImmutable()) {
                throw new \DomainException('This offer has expired.');
            }

            return match ($action) {
                'accept' => $this->accept($offer, $isSeller),
                'decline' => $this->decline($offer, $isSeller),
                'withdraw' => $this->withdraw($offer, $isBuyer),
                'cancel' => $this->cancel($offer),
                'confirm' => $this->confirm($offer, $isSeller),
                'dispute' => $this->dispute($offer),
                default => throw new \DomainException('Unknown action.'),
            };
        });
    }

    public function setMeetupNote(Offer $offer, User $actor, string $note): void
    {
        if (!$offer->involves($actor) || $offer->status !== Offer::ACCEPTED) {
            throw new \DomainException('You can only set a meetup note on an accepted offer.');
        }

        $note = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $note) ?? '');
        $offer->meetupNote = $note === '' ? null : mb_substr($note, 0, 300);
        $this->em->flush();
    }

    private function accept(Offer $offer, bool $isSeller): string
    {
        if (!$isSeller || $offer->status !== Offer::PENDING) {
            throw new \DomainException('This offer can no longer be accepted.');
        }

        $item = $offer->item;
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);

        if ($item->status !== Item::STATUS_FOR_SALE || $item->owner?->getId() !== $offer->seller->getId()) {
            throw new \DomainException('This item is no longer for sale, so the offer cannot be accepted.');
        }

        $trade = $offer->tradeItem;
        if ($offer->type !== Offer::TYPE_CASH) {
            if ($trade === null) {
                throw new \DomainException("The buyer's trade item no longer exists.");
            }
            $this->em->refresh($trade, LockMode::PESSIMISTIC_WRITE);
            if ($trade->status !== Item::STATUS_OWNED || $trade->owner?->getId() !== $offer->buyer->getId()) {
                throw new \DomainException("The buyer's trade item is no longer available.");
            }
            $trade->status = Item::STATUS_RESERVED;
        }

        $offer->status = Offer::ACCEPTED;
        $item->status = Item::STATUS_RESERVED;

        foreach ($this->offers->findBy(['item' => $item, 'status' => Offer::PENDING]) as $other) {
            if ($other->id !== $offer->id) {
                $other->status = Offer::DECLINED;
            }
        }

        $this->em->flush();

        return 'Offer accepted. The item is now reserved for this deal.';
    }

    private function decline(Offer $offer, bool $isSeller): string
    {
        if (!$isSeller || $offer->status !== Offer::PENDING) {
            throw new \DomainException('This offer cannot be declined.');
        }
        $offer->status = Offer::DECLINED;
        $this->em->flush();

        return 'Offer declined.';
    }

    private function withdraw(Offer $offer, bool $isBuyer): string
    {
        if (!$isBuyer || $offer->status !== Offer::PENDING) {
            throw new \DomainException('This offer cannot be withdrawn.');
        }
        $offer->status = Offer::CANCELLED;
        $this->em->flush();

        return 'Offer withdrawn.';
    }

    private function cancel(Offer $offer): string
    {
        if ($offer->status !== Offer::ACCEPTED) {
            throw new \DomainException('Only an accepted deal can be cancelled.');
        }

        $this->releaseItems($offer);
        $offer->status = Offer::CANCELLED;
        $offer->sellerConfirmed = false;
        $offer->buyerConfirmed = false;
        $this->em->flush();

        return 'Deal cancelled. The item is available again.';
    }

    private function confirm(Offer $offer, bool $isSeller): string
    {
        if ($offer->status !== Offer::ACCEPTED) {
            throw new \DomainException('This offer is not waiting for a handover.');
        }

        $item = $offer->item;
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);

        if ($item->owner?->getId() !== $offer->seller->getId()) {
            throw new \DomainException('The seller no longer owns this item. Please cancel this deal.');
        }

        if ($isSeller) {
            $offer->sellerConfirmed = true;
        } else {
            $offer->buyerConfirmed = true;
        }

        if (!($offer->sellerConfirmed && $offer->buyerConfirmed)) {
            $this->em->flush();

            return 'Your confirmation was saved. Waiting for the other side.';
        }

        $this->transfer($item, $offer->seller, $offer->buyer, $offer);
        if ($offer->tradeItem !== null) {
            $this->em->refresh($offer->tradeItem, LockMode::PESSIMISTIC_WRITE);
            $this->transfer($offer->tradeItem, $offer->buyer, $offer->seller, $offer);
        }

        $offer->status = Offer::COMPLETED;

        if ((float) $offer->amount > 0) {
            $fee = new PlatformRevenue();
            $fee->source = PlatformRevenue::SOURCE_TRANSACTION;
            $fee->amount = number_format((float) $offer->amount * PlatformRevenue::FEE_PERCENT / 100, 2, '.', '');
            $fee->user = $offer->seller;
            $fee->item = $item;
            $fee->offer = $offer;
            $this->em->persist($fee);
        }

        // Anything else still open on either item can no longer go ahead.
        $involved = array_filter([$item, $offer->tradeItem]);
        foreach ($involved as $moved) {
            foreach ($this->offers->findOpenForItem($moved) as $other) {
                if ($other->id !== $offer->id) {
                    $other->status = Offer::CANCELLED;
                }
            }
            foreach ($this->offers->findOpenUsingTradeItem($moved) as $other) {
                if ($other->id !== $offer->id) {
                    $other->status = Offer::CANCELLED;
                }
            }
        }

        $this->em->flush();

        return 'Handover complete. Ownership has been transferred.';
    }

    private function dispute(Offer $offer): string
    {
        if ($offer->status !== Offer::COMPLETED) {
            throw new \DomainException('Only a completed deal can be disputed.');
        }
        $offer->status = Offer::DISPUTED;
        $this->em->flush();

        return 'Marked as disputed. An admin can see this flag.';
    }

    private function transfer(Item $item, User $from, User $to, Offer $offer): void
    {
        $item->owner = $to;
        $item->status = Item::STATUS_OWNED;
        $item->price = null;
        $item->isFeatured = false;
        $item->featuredUntil = null;

        $history = new OwnershipHistory();
        $history->item = $item;
        $history->previousOwner = $from;
        $history->newOwner = $to;
        $history->offer = $offer;
        $this->em->persist($history);
    }

    private function releaseItems(Offer $offer): void
    {
        if ($offer->item->status === Item::STATUS_RESERVED) {
            $offer->item->status = Item::STATUS_FOR_SALE;
        }
        if ($offer->tradeItem !== null && $offer->tradeItem->status === Item::STATUS_RESERVED) {
            $offer->tradeItem->status = Item::STATUS_OWNED;
        }
    }

    /** Called when an owner takes a reserved or listed item off the market. */
    public function cancelOpenOffersFor(Item $item): void
    {
        foreach ($this->offers->findOpenForItem($item) as $offer) {
            $this->releaseItems($offer);
            $offer->status = Offer::CANCELLED;
        }
        foreach ($this->offers->findOpenUsingTradeItem($item) as $offer) {
            $offer->status = Offer::CANCELLED;
        }
    }

    /** Called before an account is deleted so other people's items are not left stuck as reserved. */
    public function cancelAllFor(User $user): void
    {
        $open = array_merge(
            $this->offers->findBy(['buyer' => $user, 'status' => Offer::OPEN]),
            $this->offers->findBy(['seller' => $user, 'status' => Offer::OPEN]),
        );

        foreach ($open as $offer) {
            $this->releaseItems($offer);
            $offer->status = Offer::CANCELLED;
        }
        $this->em->flush();
    }
}
