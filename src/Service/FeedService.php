<?php

declare(strict_types=1);

namespace App\Service;

class FeedService
{
    public function __construct(private SupabaseClient $supabase) {}

    public function getAllActive(): array
    {
        return $this->supabase->select('feeds', [
            'active' => 'eq.true',
        ]);
    }

    public function findAll(): array
    {
        return $this->supabase->select('feeds', [
            'order' => 'last_fetched_at.desc.nullslast',
        ]);
    }

    public function findOneById(string $id): ?array
    {
        $feeds = $this->supabase->select('feeds', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);

        return $feeds[0] ?? null;
    }

    public function update(string $id, array $data): array
    {
        return $this->supabase->update('feeds', 'id=eq.' . $id, $data);
    }

    public function delete(string $id): void
    {
        $this->supabase->delete('feeds', 'id=eq.' . $id);
    }

    public function updateLastFetched(string $id): void
    {
        $this->supabase->update('feeds', 'id=eq.' . $id, [
            'last_fetched_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function addFeed(string $name, string $url, string $category): array
    {
        // Validate input data
        if (empty($name) || !is_string($name)) {
            throw new \InvalidArgumentException('Feed name is required and must be a string');
        }
        
        if (empty($url) || !is_string($url)) {
            throw new \InvalidArgumentException('Feed URL is required and must be a string');
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format');
        }
        
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('URL must use HTTP or HTTPS scheme');
        }
        
        if (empty($category) || !is_string($category)) {
            throw new \InvalidArgumentException('Feed category is required and must be a string');
        }

        // Sanitize inputs
        $name = trim($name);
        $url = trim($url);
        $category = trim($category);
        
        // Validate lengths
        if (mb_strlen($name) > 200) {
            throw new \InvalidArgumentException('Feed name cannot exceed 200 characters');
        }
        
        if (mb_strlen($category) > 100) {
            throw new \InvalidArgumentException('Category cannot exceed 100 characters');
        }

        return $this->supabase->insert('feeds', [
            'name' => $name,
            'url' => $url,
            'category' => $category,
            'active' => true,
        ]);
    }
}
