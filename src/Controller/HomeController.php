<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Request $request, PostService $postService): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        return $this->render('home/index.html.twig', [
            'posts' => $postService->findPublished($limit, $offset),
            'currentPage' => $page,
        ]);
    }

    #[Route('/post/{slug}', name: 'post_show')]
    public function show(string $slug, PostService $postService): Response
    {
        $post = $postService->findOneBySlug($slug);
        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        return $this->render('home/post.html.twig', [
            'post' => $post,
        ]);
    }
}
