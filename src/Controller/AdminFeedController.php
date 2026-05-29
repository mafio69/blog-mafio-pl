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
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_feed_new');
            }

            try {
                $feedService->addFeed(
                    $request->request->get('name'),
                    $request->request->get('url'),
                    $request->request->get('category')
                );
                $this->addFlash('success', 'Feed added successfully.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', 'Validation error: ' . $e->getMessage());
                return $this->redirectToRoute('admin_feed_new');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to add feed: ' . $e->getMessage());
            }

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
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_feed_edit', ['id' => $id]);
            }

            try {
                // Validate input data
                $name = trim($request->request->get('name', ''));
                $url = trim($request->request->get('url', ''));
                $category = trim($request->request->get('category', ''));
                
                if (empty($name)) {
                    throw new \InvalidArgumentException('Feed name is required');
                }
                
                if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('Valid URL is required');
                }
                
                if (empty($category)) {
                    throw new \InvalidArgumentException('Category is required');
                }

                $feedService->update($id, [
                    'name' => $name,
                    'url' => $url,
                    'category' => $category,
                    'active' => $request->request->get('active') === '1',
                ]);
                
                $this->addFlash('success', 'Feed updated successfully.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', 'Validation error: ' . $e->getMessage());
                return $this->redirectToRoute('admin_feed_edit', ['id' => $id]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to update feed: ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_feed_index');
        }

        return $this->render('admin/feed/edit.html.twig', ['feed' => $feed]);
    }

    #[Route('/{id}/delete', name: 'admin_feed_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, FeedService $feedService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_feed_index');
        }

        try {
            $feedService->delete($id);
            $this->addFlash('success', 'Feed deleted successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to delete feed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_feed_index');
    }
}
