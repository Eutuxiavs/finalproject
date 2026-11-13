<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\Table(name: 'offers')]
class Offer
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';
    public const EXPIRED = 'expired';
    public const DISPUTED = 'disputed';

    public const TYPE_CASH = 'cash';
    public const TYPE_TRADE = 'trade';
    public const TYPE_TRADE_CASH = 'trade_cash';

    public const OPEN = [self::PENDING, self::ACCEPTED];
    public const VALID_DAYS = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Item $item = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?User $buyer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?User $seller = null;

    #[ORM\Column(length: 12, options: ['default' => 'cash'])]
    public string $type = self::TYPE_CASH;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $amount = '0.00';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Item $tradeItem = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $message = null;

    #[ORM\Column(length: 300, nullable: true)]
    public ?string $meetupNote = null;

    #[ORM\Column(length: 20)]
    public string $status = self::PENDING;

    #[ORM\Column]
    public bool $sellerConfirmed = false;

    #[ORM\Column]
    public bool $buyerConfirmed = false;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+' . self::VALID_DAYS . ' days');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function involves(User $user): bool
    {
        return $this->buyer?->getId() === $user->getId() || $this->seller?->getId() === $user->getId();
    }
}
