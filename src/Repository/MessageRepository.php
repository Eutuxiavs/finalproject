<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return Message[] */
    public function findAfter(Conversation $conversation, int $afterId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :c')
            ->andWhere('m.id > :after')
            ->setParameter('c', $conversation)
            ->setParameter('after', $afterId)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function markRead(Conversation $conversation, User $reader): void
    {
        $this->getEntityManager()->createQuery(
            'UPDATE ' . Message::class . ' m SET m.readAt = :now
             WHERE m.conversation = :c AND m.sender != :reader AND m.readAt IS NULL'
        )
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('c', $conversation)
            ->setParameter('reader', $reader)
            ->execute();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.conversation', 'c')
            ->andWhere('c.buyer = :user OR c.seller = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> conversation id => unread count */
    public function unreadByConversation(User $user): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.conversation) AS cid, COUNT(m.id) AS n')
            ->innerJoin('m.conversation', 'c')
            ->andWhere('c.buyer = :user OR c.seller = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->groupBy('m.conversation')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['cid']] = (int) $row['n'];
        }

        return $out;
    }

    public function findLast(Conversation $conversation): ?Message
    {
        return $this->findOneBy(['conversation' => $conversation], ['id' => 'DESC']);
    }
}
