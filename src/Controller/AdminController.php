<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Offer;
use App\Entity\PlatformRevenue;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\OfferRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use App\Service\OfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_dashboard')]
    public function index(UserRepository $users, ItemRepository $items, OfferRepository $offers, EntityManagerInterface $em): Response
    {
        $revenue = (string) ($em->createQuery('SELECT COALESCE(SUM(r.amount), 0) FROM ' . PlatformRevenue::class . ' r')->getSingleScalarResult());

        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Admin Dashboard',
            'stats' => [
                'users' => $users->count([]),
                'items' => $items->countAll(),
                'sale' => $items->countAll(Item::STATUS_FOR_SALE),
                'lost' => $items->countAll(Item::STATUS_LOST),
                'pending' => $offers->count(['status' => Offer::PENDING]),
                'deals' => $offers->count(['status' => Offer::COMPLETED]),
                'reports' => $em->getRepository(Report::class)->count(['status' => Report::OPEN]),
                'revenue' => $revenue,
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

    #[Route('/admin/users/{id}/{action}', name: 'admin_user_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'suspend|unsuspend|promote|demote'])]
    public function userAction(User $target, string $action, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $me */
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('admin_user_' . $target->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('app_users');
        }
        if ($target->getId() === $me->getId()) {
            $this->addFlash('error', 'You cannot change your own account here.');

            return $this->redirectToRoute('app_users');
        }

        switch ($action) {
            case 'suspend':
                $target->setIsSuspended(true);
                $this->addFlash('success', $target->getFullName() . ' was suspended and can no longer log in.');
                break;
            case 'unsuspend':
                $target->setIsSuspended(false);
                $this->addFlash('success', $target->getFullName() . ' was unsuspended.');
                break;
            case 'promote':
                $target->setRoles(['ROLE_ADMIN']);
                $this->addFlash('success', $target->getFullName() . ' is now an admin.');
                break;
            case 'demote':
                $target->setRoles([]);
                $this->addFlash('success', $target->getFullName() . ' is now a regular client.');
                break;
        }
        $em->flush();

        return $this->redirectToRoute('app_users');
    }

    #[Route('/admin/reports', name: 'app_reports')]
    public function reports(EntityManagerInterface $em): Response
    {
        return $this->render('dashboard/reports.html.twig', [
            'page_title' => 'Reports',
            'reports' => $em->getRepository(Report::class)->findBy([], ['id' => 'DESC'], 100),
        ]);
    }

    #[Route('/admin/reports/{id}/{action}', name: 'admin_report_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'dismiss|remove_item|suspend_user'])]
    public function reportAction(Report $report, string $action, Request $request, EntityManagerInterface $em, ImageUploader $uploader, OfferService $offerService): Response
    {
        if (!$this->isCsrfTokenValid('admin_report_' . $report->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('app_reports');
        }

        switch ($action) {
            case 'dismiss':
                $report->status = Report::DISMISSED;
                $this->addFlash('success', 'Report dismissed.');
                break;

            case 'remove_item':
                $item = $report->reportedItem;
                if ($item) {
                    $offerService->cancelOpenOffersFor($item);
                    $uploader->delete($item->photo);
                    $em->remove($item);
                    $report->reportedItem = null;
                }
                $report->status = Report::REVIEWED;
                $this->addFlash('success', 'The listing was removed.');
                break;

            case 'suspend_user':
                $target = $report->reportedUser;
                if ($target && !$target->isAdmin()) {
                    $target->setIsSuspended(true);
                    $report->status = Report::REVIEWED;
                    $this->addFlash('success', 'The user was suspended.');
                } else {
                    $this->addFlash('error', 'That user cannot be suspended.');
                }
                break;
        }
        $em->flush();

        return $this->redirectToRoute('app_reports');
    }

    #[Route('/admin/revenue', name: 'app_revenue')]
    public function revenue(EntityManagerInterface $em): Response
    {
        $rows = $em->getRepository(PlatformRevenue::class)->findBy([], ['id' => 'DESC'], 100);
        $total = (string) ($em->createQuery('SELECT COALESCE(SUM(r.amount), 0) FROM ' . PlatformRevenue::class . ' r')->getSingleScalarResult());

        return $this->render('dashboard/revenue.html.twig', [
            'page_title' => 'Revenue',
            'rows' => $rows,
            'total' => $total,
        ]);
    }
}
