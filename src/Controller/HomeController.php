<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(PostService $postService): Response
    {
        return $this->render('home/index.html.twig', [
            'posts' => $postService->findPublished(),
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
