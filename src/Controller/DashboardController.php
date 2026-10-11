<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Dashboard',
        ]);
    }

    #[Route('/users', name: 'app_users')]
    public function users(): Response
    {
        return $this->render('dashboard/users.html.twig', [
            'page_title' => 'Users',
        ]);
    }

    #[Route('/settings', name: 'app_settings')]
    public function settings(): Response
    {
        return $this->render('dashboard/settings.html.twig', [
            'page_title' => 'Settings',
        ]);
    }
}