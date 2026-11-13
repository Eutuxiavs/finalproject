<?php

namespace App\Service;

use App\Entity\Block;
use App\Entity\Offer;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Blocking, "verified trader" badge and rating summaries. */
class TrustService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReviewRepository $reviews,
    ) {
    }

    /** Blocking is treated as mutual: either side blocking the other stops contact. */
    public function isBlocked(User $a, User $b): bool
    {
        return (bool) $this->em->createQuery(
            'SELECT b.id FROM ' . Block::class . ' b
             WHERE (b.blocker = :a AND b.blocked = :b) OR (b.blocker = :b AND b.blocked = :a)'
        )
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function hasBlocked(User $blocker, User $blocked): bool
    {
        return $this->em->getRepository(Block::class)->findOneBy(['blocker' => $blocker, 'blocked' => $blocked]) !== null;
    }

    /** A lightweight trust signal: has completed at least one deal. */
    public function isVerified(User $user): bool
    {
        return (bool) $this->em->createQuery(
            'SELECT o.id FROM ' . Offer::class . ' o
             WHERE (o.buyer = :u OR o.seller = :u) AND o.status IN (:done)'
        )
            ->setParameter('u', $user)
            ->setParameter('done', [Offer::COMPLETED])
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /** @return array{average: float, count: int} */
    public function rating(User $user): array
    {
        return $this->reviews->summaryFor($user);
    }
}
