<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'platform_revenue')]
class PlatformRevenue
{
    public const FEE_PERCENT = 5;
    public const FEATURED_FEE = '5.00';
    public const FEATURED_DAYS = 14;

    public const SOURCE_TRANSACTION = 'transaction_fee';
    public const SOURCE_FEATURED = 'featured_listing';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 30)]
    public string $source = self::SOURCE_TRANSACTION;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $amount = '0.00';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Item $item = null;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Offer $offer = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
