<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\FeedService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/feeds')]
class AdminFeedController extends AbstractController
{
    #[Route('', name: 'admin_feed_index')]
    public function index(FeedService $feedService): Response
    {
        return $this->render('admin/feed/index.html.twig', [
            'feeds' => $feedService->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_feed_new')]
    public function new(Request $request, FeedService $feedService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('feed', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $feedService->addFeed(
                $request->request->get('name'),
                $request->request->get('url'),
                $request->request->get('category')
            );

            return $this->redirectToRoute('admin_feed_index');
        }

        return $this->render('admin/feed/edit.html.twig', ['feed' => null]);
    }

    #[Route('/{id}/edit', name: 'admin_feed_edit')]
    public function edit(string $id, Request $request, FeedService $feedService): Response
    {
        $feed = $feedService->findOneById($id);
        if (!$feed) {
            throw $this->createNotFoundException('Feed not found.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('feed', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $feedService->update($id, [
                'name' => $request->request->get('name'),
                'url' => $request->request->get('url'),
                'category' => $request->request->get('category'),
                'active' => $request->request->get('active') === '1',
            ]);

            return $this->redirectToRoute('admin_feed_index');
        }

        return $this->render('admin/feed/edit.html.twig', ['feed' => $feed]);
    }

    #[Route('/{id}/delete', name: 'admin_feed_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, FeedService $feedService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $feedService->delete($id);

        return $this->redirectToRoute('admin_feed_index');
    }
}
