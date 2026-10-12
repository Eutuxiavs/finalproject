<?php

namespace App\Controller;

use App\Entity\Item;
use App\Repository\ItemRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ItemRepository $items, UserRepository $users): Response
    {
        return $this->render('landing/index.html.twig', [
            'steps' => [
                ['I',   'Item',    'Name it, choose a category: electronics, timepieces, cameras, and beyond.'],
                ['II',  'Photos',  'Original photos, timestamped the moment the claim is filed.'],
                ['III', 'Serial',  'Serial number recorded and kept private, visible only to you.'],
                ['IV',  'Details', 'Condition, city, and notes worth recording.'],
                ['V',   'Decide',  'Keep it in your inventory, list it for sale, or report it lost.'],
            ],
            'latest' => $items->findLatest(6),
            'stats' => [
                'items' => $items->countAll(),
                'sale' => $items->countAll(Item::STATUS_FOR_SALE),
                'lost' => $items->countAll(Item::STATUS_LOST),
                'members' => $users->count([]),
            ],
            'rooms' => [
                ['bi-grid',                'Dashboard',     "An overview of everything you've filed, at a glance.",  'Your registry',   'user_registry'],
                ['bi-shop',                'Marketplace',   'Browse items for sale and help reunite lost ones.',     'Buy & sell',      'marketplace'],
                ['bi-plus-square',         'Register Item', 'Add an item, a photo, a serial, and its details.',      'One form',        'item_new'],
                ['bi-envelope-paper',      'Offers',        'Offers you received and offers you made.',              'Safe handover',   'offers'],
                ['bi-person-circle',       'Profile',       'Your name, picture, bio, and account.',                 'Your account',    'profile'],
            ],
        ]);
    }
}