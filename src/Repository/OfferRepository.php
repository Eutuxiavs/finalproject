<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offer>
 */
class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /** Marks every pending offer past its expiry date as expired. */
    public function expireDue(): int
    {
        return (int) $this->getEntityManager()->createQuery(
            'UPDATE ' . Offer::class . ' o SET o.status = :expired
             WHERE o.status = :pending AND o.expiresAt IS NOT NULL AND o.expiresAt < :now'
        )
            ->setParameter('expired', Offer::EXPIRED)
            ->setParameter('pending', Offer::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->execute();
    }

    public function countPendingForSeller(User $seller): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.seller = :seller')
            ->andWhere('o.status = :pending')
            ->setParameter('seller', $seller)
            ->setParameter('pending', Offer::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAccepted(): int
    {
        return $this->count(['status' => Offer::COMPLETED]);
    }

    /** @return Offer[] offers (pending or accepted) on an item, newest first */
    public function findOpenForItem(Item $item): array
    {
        return $this->findBy(['item' => $item, 'status' => Offer::OPEN], ['id' => 'DESC']);
    }

    /** @return Offer[] open offers that use the given item as their trade item */
    public function findOpenUsingTradeItem(Item $item): array
    {
        return $this->findBy(['tradeItem' => $item, 'status' => Offer::OPEN]);
    }

    public function findPendingFor(Item $item, User $buyer): ?Offer
    {
        return $this->findOneBy(['item' => $item, 'buyer' => $buyer, 'status' => Offer::PENDING]);
    }
}
