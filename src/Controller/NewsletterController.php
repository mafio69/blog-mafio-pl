<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/newsletter')]
class NewsletterController extends AbstractController
{
    #[Route('', name: 'newsletter_index')]
    public function index(Request $request, PostService $postService): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // For newsletter articles, we'll use a different method since they're in a different table
        $newsletterArticles = $this->getNewsletterArticles($postService, $limit, $offset);

        return $this->render('newsletter/index.html.twig', [
            'articles' => $newsletterArticles,
            'currentPage' => $page,
        ]);
    }

    #[Route('/{id}', name: 'newsletter_article_show')]
    public function show(string $id, PostService $postService): Response
    {
        // Get article from newsletter_articles table
        $article = $this->getNewsletterArticleById($postService, $id);
        
        if (!$article) {
            throw $this->createNotFoundException('Article not found.');
        }

        return $this->render('newsletter/show.html.twig', [
            'article' => $article,
        ]);
    }

    /**
     * Helper method to get newsletter articles from Supabase
     */
    private function getNewsletterArticles(PostService $postService, int $limit, int $offset): array
    {
        // Since PostService works with 'posts' table, we need to extend it or create a new service
        // For now, we'll use the existing Supabase client directly
        $supabase = $postService->getSupabaseClient();

        return $supabase->select('newsletter_articles', [
            'order' => 'created_at.desc',
            'limit' => min($limit, 500),
            'offset' => max(0, $offset),
        ]);
    }

    /**
     * Helper method to get a single newsletter article by ID
     */
    private function getNewsletterArticleById(PostService $postService, string $id): ?array
    {
        $supabase = $postService->getSupabaseClient();

        $articles = $supabase->select('newsletter_articles', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);

        return $articles[0] ?? null;
    }
}
