<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Entity\Block;
use App\Repository\ReviewRepository;
use App\Service\ImageUploader;
use App\Service\OfferService;
use App\Service\TrustService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, ImageUploader $uploader, ItemRepository $items): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }

            $fullName = trim((string) $request->request->get('fullName'));
            $business = trim((string) $request->request->get('business'));
            $bio = trim((string) $request->request->get('bio'));

            if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
                $errors[] = 'Your name must be between 2 and 120 characters.';
            }
            if (mb_strlen($business) > 160) {
                $errors[] = 'Business / related to can be up to 160 characters.';
            }
            if (mb_strlen($bio) > 500) {
                $errors[] = 'Your bio can be up to 500 characters.';
            }

            $file = $request->files->get('avatar');
            if ($file && ($photoError = $uploader->validate($file))) {
                $errors[] = $photoError;
            }

            if (!$errors) {
                $user->setFullName($fullName);
                $user->setBusiness($business ?: null);
                $user->setBio($bio ?: null);

                if ($file) {
                    $uploader->delete($user->getAvatar());
                    $user->setAvatar($uploader->save($file, 'avatars'));
                }

                $em->flush();
                $this->addFlash('success', 'Profile saved.');

                return $this->redirectToRoute('profile');
            }
        }

        $mine = $items->findBy(['owner' => $user]);

        return $this->render('app/profile.html.twig', [
            'errors' => $errors,
            'stats' => [
                'filed' => count($mine),
                'sale' => count(array_filter($mine, fn (Item $i) => $i->status === Item::STATUS_FOR_SALE)),
                'lost' => count(array_filter($mine, fn (Item $i) => $i->status === Item::STATUS_LOST)),
            ],
        ]);
    }

    #[Route('/profile/delete', name: 'profile_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        TokenStorageInterface $tokenStorage,
        OfferService $offerService,
        ImageUploader $uploader,
        ItemRepository $items,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('profile');
        }

        if (!$hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'That password is not correct, so your account was not deleted.');

            return $this->redirectToRoute('profile');
        }

        $offerService->cancelAllFor($user);
        foreach ($items->findBy(['owner' => $user]) as $item) {
            $uploader->delete($item->photo);
        }
        $uploader->delete($user->getAvatar());

        $em->remove($user);
        $em->flush();

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_home');
    }

    #[Route('/u/{id}', name: 'profile_public', requirements: ['id' => '\d+'])]
    public function publicProfile(User $member, ItemRepository $items, ReviewRepository $reviews, TrustService $trust): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $isMe = $member->getId() === $me->getId();

        return $this->render('app/profile_public.html.twig', [
            'member' => $member,
            'isMe' => $isMe,
            'listings' => $items->findPublicByOwner($member),
            'reviews' => $reviews->latestFor($member),
            'rating' => $trust->rating($member),
            'verified' => $trust->isVerified($member),
            'blockedByMe' => !$isMe && $trust->hasBlocked($me, $member),
        ]);
    }

    #[Route('/u/{id}/{action}', name: 'profile_block', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'block|unblock'])]
    public function block(User $member, string $action, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $me */
        $me = $this->getUser();

        if (!$this->isCsrfTokenValid('block_' . $member->getId(), (string) $request->request->get('_token')) || $member->getId() === $me->getId()) {
            $this->addFlash('error', 'That action could not be completed.');

            return $this->redirectToRoute('marketplace');
        }

        $repo = $em->getRepository(Block::class);
        $existing = $repo->findOneBy(['blocker' => $me, 'blocked' => $member]);

        if ($action === 'block' && !$existing) {
            $block = new Block();
            $block->blocker = $me;
            $block->blocked = $member;
            $em->persist($block);
            $this->addFlash('success', 'User blocked. You cannot message each other or trade until you unblock them.');
        } elseif ($action === 'unblock' && $existing) {
            $em->remove($existing);
            $this->addFlash('success', 'User unblocked.');
        }
        $em->flush();

        return $this->redirectToRoute('profile_public', ['id' => $member->getId()]);
    }
}
