<?php

namespace App\Twig;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var array{offers: int, messages: int}|null */
    private ?array $counts = null;

    /** @var array{reports: int, disputes: int}|null */
    private ?array $staffCounts = null;

    public function __construct(
        private Security $security,
        private OfferRepository $offers,
        private MessageRepository $messages,
        private EntityManagerInterface $em,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_counts', $this->navCounts(...)),
            new TwigFunction('staff_counts', $this->staffCounts(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('status_label', $this->statusLabel(...)),
            new TwigFilter('stars', $this->stars(...)),
        ];
    }

    /** Badge numbers for the top navigation. Cached for the request. */
    public function navCounts(): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->counts = ['offers' => 0, 'messages' => 0];
        }

        return $this->counts = [
            'offers' => $this->offers->countPendingForSeller($user),
            'messages' => $this->messages->countUnread($user),
        ];
    }

    /** Open-work badges for the staff sidebar. Cached for the request. */
    public function staffCounts(): array
    {
        return $this->staffCounts ??= [
            'reports' => $this->em->getRepository(Report::class)->count(['status' => Report::OPEN]),
            'disputes' => $this->em->getRepository(Offer::class)->count(['status' => Offer::DISPUTED]),
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            Item::STATUS_OWNED => 'In inventory',
            Item::STATUS_FOR_SALE => 'For sale',
            Item::STATUS_RESERVED => 'Reserved',
            Item::STATUS_LOST => 'Lost',
            Offer::PENDING => 'Pending',
            Offer::ACCEPTED => 'Accepted',
            Offer::DECLINED => 'Declined',
            Offer::CANCELLED => 'Cancelled',
            Offer::COMPLETED => 'Completed',
            Offer::EXPIRED => 'Expired',
            Offer::DISPUTED => 'Disputed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function stars(float|int $rating): string
    {
        $full = (int) round($rating);

        return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    }
}