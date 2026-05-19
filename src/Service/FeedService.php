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
            'order' => 'created_at.desc',
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
        return $this->supabase->insert('feeds', [
            'name' => $name,
            'url' => $url,
            'category' => $category,
            'active' => true,
        ]);
    }
}
