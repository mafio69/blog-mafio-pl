<?php

declare(strict_types=1);

namespace App\Service;

class PostService
{
    public function __construct(private SupabaseClient $supabase) {}

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
            $data['slug'] = $this->slugify($data['title']);
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

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}
