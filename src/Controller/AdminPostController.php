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
    public function index(PostService $postService): Response
    {
        return $this->render('admin/post/index.html.twig', [
            'posts' => $postService->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_post_new')]
    public function new(Request $request, PostService $postService): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('post', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $postService->createFromRequest($request);

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
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $postService->updateFromRequest($id, $request);

            return $this->redirectToRoute('admin_post_index');
        }

        return $this->render('admin/post/edit.html.twig', ['post' => $post]);
    }

    #[Route('/{id}/toggle', name: 'admin_post_toggle', methods: ['POST'])]
    public function toggle(string $id, Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $postService->toggleStatus($id);

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/delete', name: 'admin_post_delete', methods: ['POST'])]
    public function delete(string $id, Request $request, PostService $postService): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $postService->delete($id);

        return $this->redirectToRoute('admin_post_index');
    }

    #[Route('/{id}/regenerate-title', name: 'admin_post_regenerate_title', methods: ['POST'])]
    public function regenerateTitle(string $id, Request $request, PostService $postService, SummarizerService $summarizer): Response
    {
        if (!$this->isCsrfTokenValid('regen' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $post = $postService->findOneById($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        try {
            $title = $summarizer->generateTitle($post['content']);
            $postService->update($id, ['title' => $title]);
            $this->addFlash('success', 'Tytuł wygenerowany: ' . $title);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Błąd: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_post_index');
    }
}
