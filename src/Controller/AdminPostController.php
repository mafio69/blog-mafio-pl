<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PostService;
use App\Service\SummarizerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/posts')]
class AdminPostController extends AbstractController
{
    #[Route('', name: 'admin_post_index')]
    public function index(Request $request, PostService $postService): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        return $this->render('admin/post/index.html.twig', [
            'posts' => $postService->findAll($limit, $offset),
            'currentPage' => $page,
        ]);
    }

    #[Route('/new', name: 'admin_post_new')]
    public function new(Request $request, PostService $postService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('post', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_post_new');
            }

            try {
                $postService->createFromRequest($request);
                $this->addFlash('success', 'Post created successfully.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', 'Validation error: ' . $e->getMessage());
                return $this->redirectToRoute('admin_post_new');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to create post: ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_post_index');
        }

        return $this->render('admin/post/edit.html.twig', ['post' => null]);
    }

    #[Route('/fetch-url', name: 'admin_post_fetch_url', methods: ['POST'])]
    public function fetchUrl(Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('fetch_url', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $url = $request->request->get('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addFlash('error', 'Invalid URL provided.');
            return $this->redirectToRoute('admin_post_index');
        }

        try {
            $postService->createFromUrl($url);
            $this->addFlash('success', 'Article generated from URL. Review and publish.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to fetch article: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/edit', name: 'admin_post_edit')]
    public function edit(string $id, Request $request, PostService $postService): Response
    {
        $post = $postService->findOneById($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('post', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_post_edit', ['id' => $id]);
            }

            try {
                $postService->updateFromRequest($id, $request);
                $this->addFlash('success', 'Post updated successfully.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', 'Validation error: ' . $e->getMessage());
                return $this->redirectToRoute('admin_post_edit', ['id' => $id]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to update post: ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_post_index');
        }

        return $this->render('admin/post/edit.html.twig', ['post' => $post]);
    }

    #[Route('/{id}/toggle', name: 'admin_post_toggle', methods: ['POST'])]
    public function toggle(string $id, Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_post_index');
        }

        try {
            $postService->toggleStatus($id);
            $this->addFlash('success', 'Post status updated.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to toggle status: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/delete', name: 'admin_post_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_post_index');
        }

        try {
            $postService->delete($id);
            $this->addFlash('success', 'Post deleted successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to delete post: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/regenerate-title', name: 'admin_post_regenerate_title', methods: ['POST'])]
    public function regenerateTitle(string $id, Request $request, PostService $postService, SummarizerService $summarizer): Response
    {
        if (!$this->isCsrfTokenValid('regen' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_post_index');
        }

        $post = $postService->findOneById($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        try {
            $title = $summarizer->generateTitle($post['content']);
            $postService->update($id, ['title' => $title]);
            $this->addFlash('success', 'Tytuł wygenerowany: ' . $title);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Błąd: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_post_index');
    }
}
