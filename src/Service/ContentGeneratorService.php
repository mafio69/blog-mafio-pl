<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * ContentGeneratorService — generuje artykuły, felietony i wyniki viral research.
 *
 * Używa PerplexityClient (Sonar) zamiast Gemini — Perplexity ma dostęp
 * do internetu w czasie rzeczywistym, więc artykuły są aktualne.
 *
 * Wyniki zapisuje w Supabase:
 *  - artykuł / felieton → newsletter_articles (status: draft)
 *  - viral research     → virals (status: NEW_CANDIDATE)
 */
class ContentGeneratorService
{
    public function __construct(
        private readonly PerplexityClient $perplexity,
        private readonly SupabaseClient   $supabase,
        private readonly LoggerInterface  $logger,
    ) {}

    // =========================================================================
    // Publiczne API
    // =========================================================================

    /**
     * Generuje artykuł i zapisuje w newsletter_articles.
     *
     * @param  array{
     *   prompt: string,
     *   newsletter_type: string,
     *   section: string,
     *   tone: string,
     *   length: string,
     *   tag: string,
     *   is_lead: bool,
     * } $params
     * @return array  Wstawiony wiersz z Supabase
     */
    public function generateArticle(array $params): array
    {
        ['prompt' => $prompt, 'tone' => $tone, 'length' => $length] = $params;

        $wordRange = match ($length) {
            'short' => '200–300',
            'long'  => '800–1200',
            default => '400–600',
        };

        $toneDesc = match ($tone) {
            'neutral'   => 'neutralny, reporterski, rzeczowy',
            'technical' => 'techniczny, ekspercki, dla developerów',
            'casual'    => 'luźny, gawędziarski, bez żargonu',
            default     => 'opinionowany, redakcyjny, nie bój się oceniać',
        };

        $system = <<<SYSTEM
Jesteś redaktorem polskiego newslettera tech "Sygnał i Szum".
Piszesz po POLSKU. Ton: {$toneDesc}.
Długość: {$wordRange} słów.

Zwróć TYLKO JSON (bez tekstu przed/po) w formacie:
{
  "title": "string — chwytliwy tytuł artykułu",
  "summary": "string — 1 zdanie lead (pogrubiony wstęp)",
  "body": "string — treść artykułu w akapitach, bez markdown headers",
  "our_opinion": "string — krótka redakcyjna opinia 1-3 zdania",
  "source_name": "string — główne źródło lub puste",
  "source_url": "string — URL lub puste"
}
SYSTEM;

        $raw  = $this->perplexity->chat($system, $prompt);
        $data = $this->parseJson($raw, 'article');

        $row = [
            'newsletter_type' => $params['newsletter_type'] !== 'none' ? $params['newsletter_type'] : null,
            'section'         => $params['section'] ?: null,
            'tag'             => $params['tag'] ?: null,
            'title'           => $data['title']      ?? 'Bez tytułu',
            'summary'         => $data['summary']    ?? '',
            'body'            => $data['body']       ?? '',
            'our_opinion'     => $data['our_opinion'] ?? '',
            'source_name'     => $data['source_name'] ?? null,
            'source_url'      => $data['source_url']  ?? null,
            'priority'        => $params['is_lead'] ? 1 : 2,
            'is_lead'         => $params['is_lead'],
            'is_quick_hit'    => false,
            'published_at'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $inserted = $this->supabase->insert('newsletter_articles', $row);

        $this->logger->info('ContentGenerator: artykuł wygenerowany i zapisany', [
            'id'    => $inserted[0]['id'] ?? null,
            'title' => $row['title'],
        ]);

        return $inserted[0] ?? $row;
    }

    /**
     * Generuje felieton — lżejszy, personalny, z mocną tezą.
     */
    public function generateFelieton(array $params): array
    {
        $params['tone']   = 'opinionated';
        $params['length'] = $params['length'] ?? 'medium';

        ['prompt' => $prompt] = $params;

        $wordRange = match ($params['length']) {
            'short' => '200–300',
            'long'  => '800–1200',
            default => '400–600',
        };

        $system = <<<SYSTEM
Jesteś redaktorem newslettera tech "Sygnał i Szum". Piszesz felieton PO POLSKU.
Felieton = personalny, opinionowany, możesz prowokować, masz tezę i bronisz jej.
Pierwsza osoba ("uważam", "według mnie") jest OK. Nie bądź nijaki.
Długość: {$wordRange} słów.

Zwróć TYLKO JSON:
{
  "title": "string — tytuł z tezą lub prowokacją",
  "summary": "string — 1-2 zdania wstępu / lead",
  "body": "string — treść felietonu, akapity, bez headers",
  "our_opinion": "string — skrótowa puenta lub CTA dla czytelnika",
  "source_name": "",
  "source_url": ""
}
SYSTEM;

        $raw  = $this->perplexity->chat($system, $prompt);
        $data = $this->parseJson($raw, 'felieton');

        $row = [
            'newsletter_type' => $params['newsletter_type'] !== 'none' ? $params['newsletter_type'] : null,
            'section'         => $params['section'] ?: null,
            'tag'             => $params['tag'] ?: 'FELIETON',
            'title'           => $data['title']       ?? 'Bez tytułu',
            'summary'         => $data['summary']     ?? '',
            'body'            => $data['body']        ?? '',
            'our_opinion'     => $data['our_opinion'] ?? '',
            'source_name'     => null,
            'source_url'      => null,
            'priority'        => 2,
            'is_lead'         => $params['is_lead'] ?? false,
            'is_quick_hit'    => false,
            'published_at'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $inserted = $this->supabase->insert('newsletter_articles', $row);

        return $inserted[0] ?? $row;
    }

    /**
     * Viral research — szuka wiralów na podany temat i zapisuje w tabeli virals.
     *
     * @return array{candidates: array, saved_count: int}
     */
    public function researchVirals(string $topic): array
    {
        $plCandidates    = $this->perplexity->searchVirals($topic, 'pl');
        $worldCandidates = $this->perplexity->searchVirals($topic, 'world');

        $all = array_merge($plCandidates, $worldCandidates);

        // Deduplikacja po URL
        $seen   = [];
        $unique = [];
        foreach ($all as $c) {
            $url = $c['source_url'] ?? '';
            if ($url && !isset($seen[$url])) {
                $seen[$url] = true;
                $unique[]   = $c;
            }
        }

        // Sortuj po score
        usort($unique, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        // Zapisz każdego kandydata w Supabase
        $savedCount = 0;
        foreach ($unique as &$candidate) {
            try {
                $row = [
                    'title'        => $candidate['title']      ?? '',
                    'source_url'   => $candidate['source_url'] ?? '',
                    'source_type'  => 'perplexity_search',
                    'platform'     => $candidate['platform']   ?? 'unknown',
                    'score'        => (int) ($candidate['score'] ?? 50),
                    'status'       => 'NEW_CANDIDATE',
                    'detected_at'  => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'editor_notes' => $candidate['summary'] ?? '',
                ];

                $inserted = $this->supabase->insert('virals', $row);
                $candidate['supabase_id'] = $inserted[0]['id'] ?? null;
                $savedCount++;
            } catch (\Throwable $e) {
                $this->logger->warning('ContentGenerator: nie zapisano virala', [
                    'url'   => $candidate['source_url'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'candidates'  => $unique,
            'saved_count' => $savedCount,
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function parseJson(string $raw, string $context): array
    {
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```\s*$/m', '', $clean);

        $decoded = json_decode(trim($clean), true);

        if (!is_array($decoded)) {
            $this->logger->error("ContentGenerator: JSON parse error [{$context}]", ['raw' => substr($raw, 0, 500)]);
            // Fallback — wstaw surowy tekst jako body
            return [
                'title'       => "Wygenerowano: {$context}",
                'summary'     => '',
                'body'        => $raw,
                'our_opinion' => '',
                'source_name' => '',
                'source_url'  => '',
            ];
        }

        return $decoded;
    }
}
