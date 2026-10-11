<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
class Item
{
    public const CATEGORIES = ['Electronics', 'Photography', 'Timepieces', 'Instruments', 'Footwear', 'Cycling', 'Fashion', 'Tools', 'Other'];
    public const CONDITIONS = ['New', 'Like new', 'Good', 'Fair', 'For parts'];

    public const STATUS_OWNED = 'owned';
    public const STATUS_FOR_SALE = 'for_sale';
    public const STATUS_LOST = 'lost';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 40, unique: true, nullable: true)]
    public ?string $claimId = null;

    #[ORM\Column(length: 160)]
    public string $name = '';

    #[ORM\Column(length: 60)]
    public string $category = '';

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $serialNumber = null;

    #[ORM\Column(length: 30, nullable: true)]
    public ?string $itemCondition = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $city = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $photo = null;

    #[ORM\Column(length: 20)]
    public string $status = self::STATUS_OWNED;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    public ?string $price = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?User $owner = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}