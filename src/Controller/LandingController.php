<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig', [
            'steps' => [
                ['I',   'Item',    'Name it, choose a category: electronics, timepieces, cameras, and beyond.'],
                ['II',  'Photos',  'Original photos, timestamped the moment the claim is filed.'],
                ['III', 'Serial',  'Serial number recorded and encrypted, visible only to you.'],
                ['IV',  'Receipt', 'Purchase receipt or warranty document, attached as supporting proof.'],
                ['V',   'Details', 'Review and confirm. Your claim ID and QR code are issued instantly.'],
            ],
            'items' => [
                ['Leica Camera M11',    'Photography', 'YZ-CM11-00421',  '10 Aug 2026', 'owned'],
                ['Rolex Submariner',    'Timepieces',  'YZ-RLX-00298',   '22 Jul 2026', 'warranty'],
                ['MacBook Pro 14″',     'Electronics', 'YZ-MBP14-00559', '03 Jun 2026', 'owned'],
                ['Trek Domane SL6',     'Cycling',     'YZ-TRK-00147',   '15 May 2026', 'lost'],
                ['Fender Stratocaster', 'Instruments', 'YZ-FND-00062',   '29 Apr 2026', 'owned'],
                ['Air Jordan 1 Retro',  'Footwear',    'YZ-AJ1-00810',   '11 Mar 2026', 'owned'],
            ],
            'rooms' => [
                ['bi-grid',                'Dashboard',     "An overview of everything you've filed, at a glance.",   '12 registered · 2 lost', 'user_registry'],
                ['bi-collection',          'My Claims',     'All your registered possessions, in one place.',         'Full list',              null],
                ['bi-plus-square',         'Add Claim',     'Item, photos, serial, receipt, details.',                '5 steps',                null],
                ['bi-file-earmark-text',   'Claim Details', 'The complete ownership record and its scannable code.',  'Record + QR',            null],
                ['bi-exclamation-diamond', 'Report Lost',   'Changes public status and opens finder contact.',        '3 warranties active',    null],
            ],
        ]);
    }
}