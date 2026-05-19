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

    public function findAll(): array
    {
        return $this->supabase->select('posts', [
            'order' => 'created_at.desc',
        ]);
    }

    public function findPublished(): array
    {
        return $this->supabase->select('posts', [
            'status' => 'eq.published',
            'order' => 'published_at.desc',
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
        if (!isset($data['slug']) && isset($data['title'])) {
            $data['slug'] = $this->generateSlug($data['title']);
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
        $title = $this->extractTitle($content);

        return $this->create([
            'title' => $title,
            'content' => $content,
            'summary' => mb_substr($content, 0, 300) . '...',
            'status' => 'draft',
            'auto_generated' => true,
            'source_urls' => [$url],
            'tags' => [],
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

        return empty($text) ? 'n-a' : $text;
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

    private function extractTitle(string $content): string
    {
        $firstLine = strtok($content, "\n");
        if (mb_strlen($firstLine) > 100) {
            $firstLine = mb_substr($firstLine, 0, 100);
        }

        return $firstLine ?: 'Untitled Article';
    }
}
