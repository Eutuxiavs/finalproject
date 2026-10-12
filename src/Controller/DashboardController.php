<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Offer;
use App\Repository\ItemRepository;
use App\Repository\OfferRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_dashboard')]
    public function index(UserRepository $users, ItemRepository $items, OfferRepository $offers): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Admin Dashboard',
            'stats' => [
                'users' => $users->count([]),
                'items' => $items->countAll(),
                'sale' => $items->countAll(Item::STATUS_FOR_SALE),
                'lost' => $items->countAll(Item::STATUS_LOST),
                'pending' => $offers->count(['status' => Offer::PENDING]),
            ],
            'recent' => $items->findBy([], ['id' => 'DESC'], 8),
        ]);
    }

    #[Route('/admin/users', name: 'app_users')]
    public function users(UserRepository $users): Response
    {
        return $this->render('dashboard/users.html.twig', [
            'page_title' => 'Users',
            'users' => $users->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/admin/settings', name: 'app_settings')]
    public function settings(): Response
    {
        return $this->render('dashboard/settings.html.twig', [
            'page_title' => 'Settings',
        ]);
    }
}