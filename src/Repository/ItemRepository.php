<?php

namespace App\Repository;

use App\Entity\Item;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 9;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /** @return Item[] */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:public)')
            ->setParameter('public', [Item::STATUS_FOR_SALE, Item::STATUS_LOST, Item::STATUS_RESERVED])
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

    /**
     * Public marketplace search. Featured listings float to the top.
     *
     * @return array{items: Item[], total: int, pages: int, page: int}
     */
    public function searchMarketplace(string $filter, string $q, string $category, int $page): array
    {
        $statuses = match ($filter) {
            'sale' => [Item::STATUS_FOR_SALE, Item::STATUS_RESERVED],
            'lost' => [Item::STATUS_LOST],
            default => [Item::STATUS_FOR_SALE, Item::STATUS_RESERVED, Item::STATUS_LOST],
        };

        $qb = $this->createQueryBuilder('i')
            ->innerJoin('i.owner', 'o')
            ->addSelect('o')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->addSelect('CASE WHEN i.isFeatured = true AND i.featuredUntil > :now THEN 1 ELSE 0 END AS HIDDEN featuredRank')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('featuredRank', 'DESC')
            ->addOrderBy('i.id', 'DESC');

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $qb->andWhere('i.name LIKE :q OR i.claimId LIKE :q OR i.city LIKE :q')
                ->setParameter('q', $like);
        }

        if ($category !== '') {
            $qb->andWhere('i.category = :category')->setParameter('category', $category);
        }

        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb->getQuery(), false);
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        return [
            'items' => iterator_to_array($paginator, false),
            'total' => $total,
            'pages' => $pages,
            'page' => min($page, $pages),
        ];
    }

    /** @return Item[] items the user can still offer in a trade */
    public function findTradeable(User $owner, ?Item $exclude = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.owner = :owner')
            ->andWhere('i.status = :owned')
            ->setParameter('owner', $owner)
            ->setParameter('owned', Item::STATUS_OWNED)
            ->orderBy('i.name', 'ASC');

        if ($exclude) {
            $qb->andWhere('i.id != :exclude')->setParameter('exclude', $exclude->id);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Item[] */
    public function findPublicByOwner(User $owner): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.owner = :owner')
            ->andWhere('i.status IN (:public)')
            ->setParameter('owner', $owner)
            ->setParameter('public', [Item::STATUS_FOR_SALE, Item::STATUS_RESERVED, Item::STATUS_LOST])
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
