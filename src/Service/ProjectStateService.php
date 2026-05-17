<?php

declare(strict_types=1);

namespace App\Service;

class ProjectStateService
{
    public function __construct(private SupabaseClient $supabase) {}

    /**
     * @return array<string, list<array{section: string, key: string, value: string, encrypted: bool, updated_at: string}>>
     */
    public function getAllGroupedBySections(): array
    {
        $rows = $this->supabase->select('project_state', [
            'select' => 'section,key,value,encrypted,updated_at',
            'order' => 'section,key',
        ]);

        $sections = [];
        foreach ($rows as $row) {
            $sections[$row['section']][] = $row;
        }

        return $sections;
    }

    public function upsert(string $section, string $key, string $value, bool $encrypted = false): void
    {
        $this->supabase->insert('project_state', [
            'section' => $section,
            'key' => $key,
            'value' => $value,
            'encrypted' => $encrypted,
        ]);
    }
}
