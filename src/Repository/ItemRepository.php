<?php

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /** @return Item[] */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('i')->select('COUNT(i.id)');

        if ($status !== null) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return Item[] */
    public function findForMarketplace(string $filter, string $q, string $category): array
    {
        $statuses = match ($filter) {
            'sale' => [Item::STATUS_FOR_SALE],
            'lost' => [Item::STATUS_LOST],
            default => [Item::STATUS_FOR_SALE, Item::STATUS_LOST],
        };

        $qb = $this->createQueryBuilder('i')
            ->innerJoin('i.owner', 'o')
            ->addSelect('o')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('i.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('i.name LIKE :q OR i.claimId LIKE :q OR i.city LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($category !== '') {
            $qb->andWhere('i.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }
}