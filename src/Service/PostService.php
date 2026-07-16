<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class PostService
{
    public function __construct(
        private SupabaseClient $supabase,
        private SummarizerService $summarizer,
    ) {}

    public function getSupabaseClient(): SupabaseClient
    {
        return $this->supabase;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return $this->supabase->select('posts', [
            'order' => 'created_at.desc',
            'limit' => min($limit, 500), // Hard cap to prevent memory issues
            'offset' => max(0, $offset),
        ]);
    }

    public function findPublished(int $limit = 50, int $offset = 0): array
    {
        return $this->supabase->select('posts', [
            'status' => 'eq.published',
            'order' => 'published_at.desc',
            'limit' => min($limit, 200),
            'offset' => max(0, $offset),
        ]);
    }

    public function findOneBySlug(string $slug): ?array
    {
        $posts = $this->supabase->select('posts', [
            'slug' => 'eq.' . $slug,
            'limit' => 1,
        ]);

        return $posts[0] ?? null;
    }

    public function findOneById(string $id): ?array
    {
        $posts = $this->supabase->select('posts', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);

        return $posts[0] ?? null;
    }

    public function create(array $data): array
    {
        // Validate required fields
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Title is required');
        }
        
        if (empty($data['content'])) {
            throw new \InvalidArgumentException('Content is required');
        }

        // Sanitize and validate data
        $data['title'] = trim($data['title']);
        if (mb_strlen($data['title']) > 200) {
            throw new \InvalidArgumentException('Title cannot exceed 200 characters');
        }

        if (isset($data['summary']) && mb_strlen($data['summary']) > 500) {
            $data['summary'] = mb_substr($data['summary'], 0, 500) . '...';
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = array_filter(
                array_map(fn($tag) => is_string($tag) ? trim($tag) : null, $data['tags']),
                fn($tag) => !empty($tag) && strlen($tag) <= 30
            );
        }

        if (isset($data['source_urls']) && is_array($data['source_urls'])) {
            $data['source_urls'] = array_filter(
                array_map(fn($url) => filter_var(trim($url), FILTER_VALIDATE_URL) ?: null, $data['source_urls']),
            );
        }

        if (!isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        } else {
            $data['slug'] = $this->sanitizeSlug($data['slug']);
        }

        // Set default status if not provided
        $data['status'] ??= 'draft';

        // Validate status
        if (!in_array($data['status'], ['draft', 'published', 'archived'], true)) {
            throw new \InvalidArgumentException('Invalid status: ' . $data['status']);
        }

        return $this->supabase->insert('posts', $data);
    }

    public function update(string $id, array $data): array
    {
        return $this->supabase->update('posts', 'id=eq.' . $id, $data);
    }

    public function delete(string $id): void
    {
        $this->supabase->delete('posts', 'id=eq.' . $id);
    }

    public function toggleStatus(string $id): void
    {
        $post = $this->findOneById($id);
        if (!$post) {
            return;
        }

        $newStatus = $post['status'] === 'published' ? 'draft' : 'published';
        $data = ['status' => $newStatus];

        if ($newStatus === 'published') {
            $data['published_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        }

        $this->update($id, $data);
    }

    public function createFromRequest(Request $request): array
    {
        return $this->create($this->extractPostData($request));
    }

    public function updateFromRequest(string $id, Request $request): array
    {
        return $this->update($id, $this->extractPostData($request));
    }

    public function createFromUrl(string $url): array
    {
        $content = $this->summarizer->summarizeUrl($url);
        $title = $this->summarizer->generateTitle($content);
        $tags = $this->summarizer->generateTags($content);

        return $this->create([
            'title' => $title,
            'content' => $content,
            'summary' => mb_substr($content, 0, 300) . '...',
            'status' => 'draft',
            'auto_generated' => true,
            'source_urls' => [$url],
            'tags' => $tags,
        ]);
    }

    public function generateSlug(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a-' . bin2hex(random_bytes(4));
        }

        // Ensure uniqueness by checking existing slugs
        $originalSlug = $text;
        $counter = 1;
        
        while ($this->findOneBySlug($text) !== null) {
            $text = $originalSlug . '-' . $counter;
            $counter++;
            
            if ($counter > 100) {
                return $originalSlug . '-' . bin2hex(random_bytes(4));
            }
        }

        return $text;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = trim($slug);
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        $slug = preg_replace('~-+~', '-', $slug);
        $slug = trim($slug, '-');
        $slug = strtolower($slug);
        
        if (empty($slug)) {
            throw new \InvalidArgumentException('Invalid slug');
        }

        return $slug;
    }

    private function extractPostData(Request $request): array
    {
        $tags = array_filter(array_map('trim', explode(',', $request->request->get('tags', ''))));
        $sourceUrls = array_filter(array_map('trim', explode(',', $request->request->get('source_urls', ''))));

        $data = [
            'title' => $request->request->get('title'),
            'content' => $request->request->get('content'),
            'summary' => $request->request->get('summary'),
            'status' => $request->request->get('status'),
            'tags' => $tags,
            'source_urls' => $sourceUrls,
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
