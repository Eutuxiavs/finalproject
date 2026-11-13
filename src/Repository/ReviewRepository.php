<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return array{average: float, count: int} */
    public function summaryFor(User $user): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS average, COUNT(r.id) AS total')
            ->andWhere('r.reviewee = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return ['average' => (float) ($row['average'] ?? 0), 'count' => (int) $row['total']];
    }

    /** @return Review[] */
    public function latestFor(User $user, int $limit = 10): array
    {
        return $this->findBy(['reviewee' => $user], ['id' => 'DESC'], $limit);
    }
}
