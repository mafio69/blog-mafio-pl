<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\FeedService;
use App\Service\PostService;
use App\Service\ProjectStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/project-state', name: 'admin_project_state')]
    public function projectState(ProjectStateService $projectState): Response
    {
        return $this->render('admin/project_state.html.twig', [
            'sections' => $projectState->getAllGroupedBySections(),
        ]);
    }

    #[Route('/feeds', name: 'admin_feed_index')]
    public function feedIndex(FeedService $feedService): Response
    {
        return $this->render('admin/feed/index.html.twig', [
            'feeds' => $feedService->findAll(),
        ]);
    }

    #[Route('/feeds/new', name: 'admin_feed_new')]
    public function feedNew(Request $request, FeedService $feedService): Response
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

        return $this->render('admin/feed/edit.html.twig', [
            'feed' => null,
        ]);
    }

    #[Route('/feeds/{id}/edit', name: 'admin_feed_edit')]
    public function feedEdit(string $id, Request $request, FeedService $feedService): Response
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

        return $this->render('admin/feed/edit.html.twig', [
            'feed' => $feed,
        ]);
    }

    #[Route('/feeds/{id}/delete', name: 'admin_feed_delete', methods: ['POST'])]
    public function feedDelete(string $id, Request $request, FeedService $feedService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $feedService->delete($id);

        return $this->redirectToRoute('admin_feed_index');
    }

    #[Route('/posts', name: 'admin_post_index')]
    public function postIndex(PostService $postService): Response
    {
        return $this->render('admin/post/index.html.twig', [
            'posts' => $postService->findAll(),
        ]);
    }

    #[Route('/posts/new', name: 'admin_post_new')]
    public function postNew(Request $request, PostService $postService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('post', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $data = $this->getPostDataFromRequest($request);
            $postService->create($data);

            return $this->redirectToRoute('admin_post_index');
        }

        return $this->render('admin/post/edit.html.twig', [
            'post' => null,
        ]);
    }

    #[Route('/posts/{id}/edit', name: 'admin_post_edit')]
    public function postEdit(string $id, Request $request, PostService $postService): Response
    {
        $post = $postService->findOneById($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('post', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $data = $this->getPostDataFromRequest($request);
            $postService->update($id, $data);

            return $this->redirectToRoute('admin_post_index');
        }

        return $this->render('admin/post/edit.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/posts/{id}/delete', name: 'admin_post_delete', methods: ['POST'])]
    public function postDelete(string $id, Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $postService->delete($id);

        return $this->redirectToRoute('admin_post_index');
    }

    private function getPostDataFromRequest(Request $request): array
    {
        $tags = array_map('trim', explode(',', $request->request->get('tags', '')));
        $sourceUrls = array_map('trim', explode(',', $request->request->get('source_urls', '')));

        $data = [
            'title' => $request->request->get('title'),
            'content' => $request->request->get('content'),
            'summary' => $request->request->get('summary'),
            'status' => $request->request->get('status'),
            'tags' => array_filter($tags),
            'source_urls' => array_filter($sourceUrls),
        ];

        $slug = $request->request->get('slug');
        if ($slug) {
            $data['slug'] = $slug;
        }

        if ($data['status'] === 'published') {
            $data['published_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        }

        return $data;
    }
}
