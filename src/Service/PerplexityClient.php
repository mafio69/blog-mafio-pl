<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Klient Perplexity Sonar API.
 * Wzorowany na GeminiClient — retry, logowanie, autowire.
 *
 * Wymaga w .env:
 *   PERPLEXITY_API_KEY=pplx-...
 *
 * W services.yaml dodaj:
 *   bind:
 *     string $perplexityApiKey: '%env(PERPLEXITY_API_KEY)%'
 */
class PerplexityClient
{
    private const API_URL    = 'https://api.perplexity.ai/chat/completions';
    private const MODEL      = 'sonar';
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $perplexityApiKey,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Wyszukuje i generuje treść na podstawie promptu.
     * Perplexity Sonar automatycznie przeszukuje internet.
     *
     * @param  string $systemPrompt  Rola / instrukcja systemowa
     * @param  string $userPrompt    Właściwe zapytanie
     * @param  int    $maxTokens     Limit tokenów odpowiedzi
     * @return string                Treść odpowiedzi modelu
     */
    public function chat(string $systemPrompt, string $userPrompt, int $maxTokens = 2048): string
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->perplexityApiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model'    => self::MODEL,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userPrompt],
                        ],
                        'max_tokens'             => $maxTokens,
                        'temperature'            => 0.7,
                        'search_recency_filter'  => 'week',
                        'return_citations'       => true,
                    ],
                    'timeout' => 45,
                ]);

                $data = $response->toArray();

                if (isset($data['error'])) {
                    throw new \RuntimeException('Perplexity API: ' . ($data['error']['message'] ?? 'unknown error'));
                }

                return $data['choices'][0]['message']['content']
                    ?? throw new \RuntimeException('Perplexity API: brak treści w odpowiedzi');

            } catch (\Throwable $e) {
                $this->logger->error('PerplexityClient: request failed', [
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);

                if ($attempt === self::MAX_RETRIES) {
                    throw new \RuntimeException(
                        "PerplexityClient: nieudane po {$attempt} próbach: " . $e->getMessage(),
                        previous: $e,
                    );
                }

                usleep(1_000_000 * $attempt); // 1s, 2s
            }
        }

        throw new \RuntimeException('PerplexityClient: unexpected error');
    }

    /**
     * Szybkie wyszukiwanie wiralów — zwraca JSON array kandydatów.
     *
     * @return array<int, array{title:string, source_url:string, platform:string, score:int, summary:string}>
     */
    public function searchVirals(string $topic, string $region = 'pl'): array
    {
        $regionQuery = match ($region) {
            'pl'    => "Przeszukaj POLSKI internet (Wykop, polskie blogi, serwisy tech PL) i znajdź 5 najbardziej viralowych artykułów / postów z ostatnich 7 dni na temat: {$topic}",
            'world' => "Search GLOBAL internet (Reddit, HackerNews, X/Twitter, tech blogs) for 5 most viral posts from last 7 days about: {$topic}",
            default => "Znajdź 5 viralowych treści z ostatnich 7 dni na temat: {$topic}",
        };

        $system = <<<PROMPT
Jesteś redaktorem newslettera tech. Zwróć TYLKO JSON array, bez żadnego tekstu przed ani po.
Format każdego elementu:
{
  "title": "string",
  "source_url": "string (pełny URL)",
  "platform": "string (wykop/reddit/hackernews/twitter/blog/news)",
  "score": int (1-100, ocena viralności),
  "summary": "string (1-2 zdania po polsku)"
}
PROMPT;

        $raw = $this->chat($system, $regionQuery, 1024);

        // Wyczyść ewentualne ```json ``` wrappy
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```\s*$/m', '', $clean);

        $decoded = json_decode(trim($clean), true);

        if (!is_array($decoded)) {
            $this->logger->warning('PerplexityClient: nie udało się zdekodować JSON wiralów', ['raw' => $raw]);
            return [];
        }

        return $decoded;
    }
}
