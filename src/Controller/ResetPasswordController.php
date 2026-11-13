<?php

namespace App\Controller;

use App\Entity\PasswordReset;
use App\Entity\User;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * No mail server is configured in this project, so the reset link is shown on
 * screen instead of being emailed. In production it would be sent by email.
 */
class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgot(Request $request, UserRepository $users, EntityManagerInterface $em): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_after_login');
        }

        $link = null;
        $sent = false;

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('forgot', (string) $request->request->get('_token'))) {
            $sent = true;
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $user = $users->findOneBy(['email' => $email]);

            if ($user) {
                $reset = new PasswordReset();
                $reset->user = $user;
                $em->persist($reset);
                $em->flush();
                $link = $this->generateUrl('app_reset_password', ['token' => $reset->token]);
            }
        }

        return $this->render('security/forgot_password.html.twig', ['sent' => $sent, 'link' => $link]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function reset(string $token, Request $request, PasswordResetRepository $resets, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $reset = $resets->findUsable($token);
        $errors = [];

        if (!$reset) {
            return $this->render('security/reset_password.html.twig', ['valid' => false, 'errors' => [], 'token' => $token]);
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('confirm');

            if (!$this->isCsrfTokenValid('reset', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if (mb_strlen($password) < 8) {
                $errors[] = 'Your password must be at least 8 characters.';
            }
            if ($password !== $confirm) {
                $errors[] = 'The two passwords do not match.';
            }

            if (!$errors) {
                /** @var User $user */
                $user = $reset->user;
                $user->setPassword($hasher->hashPassword($user, $password));
                $reset->used = true;
                $em->flush();

                $this->addFlash('success', 'Password updated. You can log in now.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', ['valid' => true, 'errors' => $errors, 'token' => $token]);
    }
}
