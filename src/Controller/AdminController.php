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

/**
 * Back office for staff and admins.
 * Staff: moderate reports, listings and disputes, and suspend client accounts.
 * Admins: everything staff can do, plus revenue and changing roles.
 */
#[IsGranted('ROLE_STAFF')]
class AdminController extends AbstractController
{
    private const MODERATED = [Item::STATUS_FOR_SALE, Item::STATUS_RESERVED, Item::STATUS_LOST];

    public function __construct(
        private EntityManagerInterface $em,
        private ImageUploader $uploader,
        private OfferService $offerService,
    ) {
    }

    #[Route('/admin', name: 'app_dashboard')]
    public function index(UserRepository $users, ItemRepository $items, OfferRepository $offers): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        return $this->render('dashboard/index.html.twig', [
            'page_title' => $isAdmin ? 'Admin Dashboard' : 'Staff Dashboard',
            'stats' => [
                'users' => $users->count([]),
                'items' => $items->countAll(),
                'sale' => $items->countAll(Item::STATUS_FOR_SALE),
                'lost' => $items->countAll(Item::STATUS_LOST),
                'pending' => $offers->count(['status' => Offer::PENDING]),
                'deals' => $offers->count(['status' => Offer::COMPLETED]),
                'disputes' => $offers->count(['status' => Offer::DISPUTED]),
                'reports' => $this->em->getRepository(Report::class)->count(['status' => Report::OPEN]),
                'revenue' => $isAdmin ? $this->totalRevenue() : null,
            ],
            'recent' => $items->findBy([], ['id' => 'DESC'], 8),
        ]);
    }

    // ------------------------------------------------------------ users

    #[Route('/admin/users', name: 'app_users')]
    public function users(UserRepository $users): Response
    {
        return $this->render('dashboard/users.html.twig', [
            'page_title' => 'Users',
            'users' => $users->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/admin/users/{id}/{action}', name: 'admin_user_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'suspend|unsuspend|make_client|make_staff|make_admin'])]
    public function userAction(User $target, string $action, Request $request): Response
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

        $iAmAdmin = $this->isGranted('ROLE_ADMIN');
        $isRoleChange = str_starts_with($action, 'make_');

        if ($isRoleChange && !$iAmAdmin) {
            $this->addFlash('error', 'Only admins can change roles.');

            return $this->redirectToRoute('app_users');
        }
        // Nobody can suspend an admin, and staff cannot touch other staff.
        if (!$isRoleChange && ($target->isAdmin() || (!$iAmAdmin && $target->isStaff()))) {
            $this->addFlash('error', 'You cannot change that account.');

            return $this->redirectToRoute('app_users');
        }

        $name = $target->getFullName();
        switch ($action) {
            case 'suspend':
                $target->setIsSuspended(true);
                $this->addFlash('success', $name . ' was suspended and can no longer log in.');
                break;
            case 'unsuspend':
                $target->setIsSuspended(false);
                $this->addFlash('success', $name . ' was unsuspended.');
                break;
            case 'make_client':
                $target->setRoles([]);
                $this->addFlash('success', $name . ' is now a regular client.');
                break;
            case 'make_staff':
                $target->setRoles(['ROLE_STAFF']);
                $this->addFlash('success', $name . ' is now a staff member.');
                break;
            case 'make_admin':
                $target->setRoles(['ROLE_ADMIN']);
                $this->addFlash('success', $name . ' is now an admin.');
                break;
        }
        $this->em->flush();

        return $this->redirectToRoute('app_users');
    }

    // ---------------------------------------------------------- reports

    #[Route('/admin/reports', name: 'app_reports')]
    public function reports(): Response
    {
        return $this->render('dashboard/reports.html.twig', [
            'page_title' => 'Reports',
            'reports' => $this->em->getRepository(Report::class)->findBy([], ['id' => 'DESC'], 100),
        ]);
    }

    #[Route('/admin/reports/{id}/{action}', name: 'admin_report_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'dismiss|remove_item|suspend_user'])]
    public function reportAction(Report $report, string $action, Request $request): Response
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
                if ($report->reportedItem) {
                    $this->removeListing($report->reportedItem);
                    $report->reportedItem = null;
                }
                $report->status = Report::REVIEWED;
                $this->addFlash('success', 'The listing was removed.');
                break;

            case 'suspend_user':
                $target = $report->reportedUser;
                $allowed = $target && !$target->isAdmin() && ($this->isGranted('ROLE_ADMIN') || !$target->isStaff());
                if ($allowed) {
                    $target->setIsSuspended(true);
                    $report->status = Report::REVIEWED;
                    $this->addFlash('success', 'The user was suspended.');
                } else {
                    $this->addFlash('error', 'That user cannot be suspended.');
                }
                break;
        }
        $this->em->flush();

        return $this->redirectToRoute('app_reports');
    }

    // --------------------------------------------------------- listings

    #[Route('/admin/listings', name: 'app_listings')]
    public function listings(Request $request, ItemRepository $items): Response
    {
        $filter = (string) $request->query->get('status', 'all');
        $statuses = in_array($filter, self::MODERATED, true) ? [$filter] : self::MODERATED;

        return $this->render('dashboard/listings.html.twig', [
            'page_title' => 'Listings',
            'items' => $items->findBy(['status' => $statuses], ['id' => 'DESC'], 100),
            'filter' => in_array($filter, self::MODERATED, true) ? $filter : 'all',
        ]);
    }

    #[Route('/admin/listings/{id}/remove', name: 'admin_listing_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function listingRemove(Item $item, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_listing_' . $item->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('app_listings');
        }
        if (!in_array($item->status, self::MODERATED, true)) {
            $this->addFlash('error', 'Only public listings can be removed here.');

            return $this->redirectToRoute('app_listings');
        }

        $name = $item->name;
        $this->removeListing($item);
        $this->em->flush();
        $this->addFlash('success', sprintf('"%s" was removed from the marketplace.', $name));

        return $this->redirectToRoute('app_listings');
    }

    // ---------------------------------------------------------- disputes

    #[Route('/admin/disputes', name: 'app_disputes')]
    public function disputes(OfferRepository $offers): Response
    {
        return $this->render('dashboard/disputes.html.twig', [
            'page_title' => 'Disputes',
            'offers' => $offers->findBy(['status' => Offer::DISPUTED], ['id' => 'DESC']),
        ]);
    }

    #[Route('/admin/disputes/{id}/resolve', name: 'admin_dispute_resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function disputeResolve(Offer $offer, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_dispute_' . $offer->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('app_disputes');
        }

        if ($offer->status === Offer::DISPUTED) {
            $offer->status = Offer::COMPLETED;
            $this->em->flush();
            $this->addFlash('success', 'Dispute marked as resolved.');
        }

        return $this->redirectToRoute('app_disputes');
    }

    // ----------------------------------------------------------- revenue

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/revenue', name: 'app_revenue')]
    public function revenue(): Response
    {
        return $this->render('dashboard/revenue.html.twig', [
            'page_title' => 'Revenue',
            'rows' => $this->em->getRepository(PlatformRevenue::class)->findBy([], ['id' => 'DESC'], 100),
            'total' => $this->totalRevenue(),
        ]);
    }

    // ----------------------------------------------------------- helpers

    private function totalRevenue(): string
    {
        return (string) $this->em
            ->createQuery('SELECT COALESCE(SUM(r.amount), 0) FROM ' . PlatformRevenue::class . ' r')
            ->getSingleScalarResult();
    }

    /** Takes a listing off the marketplace: cancels its open offers, deletes its photo and the item itself. */
    private function removeListing(Item $item): void
    {
        $this->offerService->cancelOpenOffersFor($item);
        $this->uploader->delete($item->photo);
        $this->em->remove($item);
    }
}