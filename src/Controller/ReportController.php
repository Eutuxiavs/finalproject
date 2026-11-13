<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportController extends AbstractController
{
    #[Route('/marketplace/{id}/report', name: 'report_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Item $item, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;

        if ($item->owner?->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot report your own listing.');

            return $this->redirectToRoute('marketplace');
        }

        if ($request->isMethod('POST')) {
            $reason = trim((string) $request->request->get('reason', ''));

            if (!$this->isCsrfTokenValid('report_' . $item->id, (string) $request->request->get('_token'))) {
                $error = 'Your session expired. Please try again.';
            } elseif ($reason === '' || mb_strlen($reason) > 500) {
                $error = 'Describe the problem in up to 500 characters.';
            } else {
                $report = new Report();
                $report->reporter = $user;
                $report->reportedUser = $item->owner;
                $report->reportedItem = $item;
                $report->itemLabel = $item->name;
                $report->reason = $reason;
                $em->persist($report);
                $em->flush();

                $this->addFlash('success', 'Thanks. Your report was sent to the moderators.');

                return $this->redirectToRoute('marketplace');
            }
        }

        return $this->render('app/report_new.html.twig', ['item' => $item, 'error' => $error]);
    }
}
