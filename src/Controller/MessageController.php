<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Item;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Service\TrustService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MessageController extends AbstractController
{
    #[Route('/messages', name: 'messages')]
    public function index(Request $request, ConversationRepository $conversations, MessageRepository $messages): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $list = $conversations->findForUser($user);
        $unread = $messages->unreadByConversation($user);

        $active = null;
        $wanted = $request->query->getInt('c');
        foreach ($list as $conversation) {
            if ($conversation->id === $wanted) {
                $active = $conversation;
            }
        }
        $active ??= $list[0] ?? null;

        $thread = [];
        if ($active) {
            $thread = $messages->findAfter($active, 0);
            $messages->markRead($active, $user);
            $unread[$active->id] = 0;
        }

        $previews = [];
        foreach ($list as $conversation) {
            $last = $messages->findLast($conversation);
            $previews[$conversation->id] = $last ? mb_substr($last->body, 0, 60) : 'No messages yet';
        }

        return $this->render('app/messages.html.twig', [
            'conversations' => $list,
            'active' => $active,
            'thread' => $thread,
            'unread' => $unread,
            'previews' => $previews,
        ]);
    }

    #[Route('/marketplace/{id}/message', name: 'message_start', requirements: ['id' => '\d+'])]
    public function start(Item $item, ConversationRepository $conversations, EntityManagerInterface $em, TrustService $trust): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$item->isPublic()) {
            $this->addFlash('error', 'That listing is no longer available.');

            return $this->redirectToRoute('marketplace');
        }
        if ($item->owner?->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot message yourself about your own listing.');

            return $this->redirectToRoute('user_registry');
        }
        if ($trust->isBlocked($user, $item->owner)) {
            $this->addFlash('error', 'You cannot contact this user.');

            return $this->redirectToRoute('marketplace');
        }

        $conversation = $conversations->findOneBy(['item' => $item, 'buyer' => $user]);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->item = $item;
            $conversation->buyer = $user;
            $conversation->seller = $item->owner;
            $em->persist($conversation);
            $em->flush();
        }

        return $this->redirectToRoute('messages', ['c' => $conversation->id]);
    }

    #[Route('/messages/{id}/send', name: 'message_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(Conversation $conversation, Request $request, EntityManagerInterface $em, TrustService $trust): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->includes($user)) {
            return $this->json(['ok' => false, 'error' => 'You are not part of this conversation.'], 403);
        }
        if (!$this->isCsrfTokenValid('chat', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 403);
        }

        $body = trim((string) $request->request->get('body', ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            return $this->json(['ok' => false, 'error' => 'A message must be between 1 and 2000 characters.'], 422);
        }
        if ($trust->isBlocked($user, $conversation->otherParty($user))) {
            return $this->json(['ok' => false, 'error' => 'You can no longer message this user.'], 403);
        }

        $message = new Message();
        $message->conversation = $conversation;
        $message->sender = $user;
        $message->body = $body;
        $em->persist($message);
        $em->flush();

        return $this->json(['ok' => true, 'message' => $this->serialize($message, $user)]);
    }

    #[Route('/messages/{id}/poll', name: 'message_poll', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function poll(Conversation $conversation, Request $request, MessageRepository $messages): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->includes($user)) {
            return $this->json(['ok' => false], 403);
        }

        $new = $messages->findAfter($conversation, $request->query->getInt('after'));
        $messages->markRead($conversation, $user);

        return $this->json([
            'ok' => true,
            'messages' => array_map(fn (Message $m) => $this->serialize($m, $user), $new),
        ]);
    }

    /** @return array{id: int, body: string, mine: bool, time: string} */
    private function serialize(Message $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender->getId() === $viewer->getId(),
            'time' => $message->createdAt->format('M j, g:i A'),
        ];
    }
}
