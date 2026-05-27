<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SummarizerService
{
    private const MAX_TEXT_LENGTH = 15000;
    private const MIN_ARTICLE_WORDS = 300;

    public function __construct(
        private HttpClientInterface $httpClient,
        private GeminiClient $geminiClient,
        private LoggerInterface $logger,
    ) {}

    public function summarizeUrl(string $url): string
    {
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException('Invalid URL provided');
        }

        try {
            // 1. Fetch content with timeout and proper headers
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; BlogAggregator/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new \RuntimeException("Failed to fetch URL: HTTP {$statusCode}");
            }

            $html = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch article content', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Could not fetch article: " . $e->getMessage(), previous: $e);
        }

        // 2. Extract clean text
        $text = $this->extractCleanText($html);

        if (empty(trim($text))) {
            throw new \RuntimeException('No readable content found at URL');
        }

        if (str_word_count($text) < self::MIN_ARTICLE_WORDS) {
            $this->logger->warning('Article appears too short', [
                'url' => $url,
                'word_count' => str_word_count($text),
            ]);
        }

        // 3. Prepare prompt
        $prompt = <<<PROMPT
Na podstawie poniższego artykułu napisz PEŁNY, SAMODZIELNY artykuł po polsku (minimum 5-7 akapitów, 800-1200 słów).

Wymagania:
- Artykuł musi być kompletny i czytelny BEZ sięgania do oryginału.
- Zacznij od mocnego wstępu wyjaśniającego kontekst i dlaczego temat jest ważny.
- Rozwiń szczegóły techniczne: jak to działa, jakie problemy rozwiązuje, przykłady użycia.
- Dodaj sekcję z praktycznymi wnioskami dla programisty.
- Zakończ podsumowaniem i perspektywą na przyszłość.
- Pisz profesjonalnie ale przystępnie, jak doświadczony bloger techniczny.
- NIE używaj nagłówków markdown ani formatowania — pisz ciągłym tekstem z akapitami.
- NIE zaczynaj od "Oto artykuł" ani podobnych meta-wstępów.

Artykuł źródłowy:
$text
PROMPT;

        // 4. Call Gemini
        try {
            $summary = $this->geminiClient->generateContent($prompt);
            
            if (empty(trim($summary))) {
                throw new \RuntimeException('Generated summary is empty');
            }

            return $summary;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate summary', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return !empty($parsed['host']);
    }

    public function generateTitle(string $content): string
    {
        if (empty(trim($content))) {
            throw new \InvalidArgumentException('Content cannot be empty');
        }

        $text = mb_substr($content, 0, 1500);

        $prompt = <<<PROMPT
Na podstawie poniższego artykułu napisz JEDEN krótki, chwytliwy tytuł po polsku (max 80 znaków).
Tytuł ma być konkretny, informacyjny i przyciągający uwagę.
NIE dodawaj cudzysłowów, kropki na końcu ani żadnego formatowania. Odpowiedz TYLKO tytułem.

Artykuł:
$text
PROMPT;

        try {
            $title = trim($this->geminiClient->generateContent($prompt, maxOutputTokens: 100));
            $title = trim($title, '"\'.');

            // Validate title
            if (empty($title)) {
                throw new \RuntimeException('Generated title is empty');
            }

            return mb_strlen($title) > 100 ? mb_substr($title, 0, 100) : $title;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate title', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function generateTags(string $content): array
    {
        if (empty(trim($content))) {
            return [];
        }

        $text = mb_substr($content, 0, 2000);

        $prompt = <<<PROMPT
Na podstawie poniższego artykułu wygeneruj 2-5 tagów (krótkich, 1-2 słowa, po angielsku, lowercase).
Tagi powinny opisywać technologie, tematy i kategorie artykułu.
Przykłady dobrych tagów: php, docker, ai, symfony, devops, security, linux, kubernetes, laravel, javascript, python, cloud, database, performance, testing

Odpowiedz WYŁĄCZNIE tablicą JSON, np: ["php", "docker", "security"]

Artykuł:
$text
PROMPT;

        try {
            $response = $this->geminiClient->generateContent($prompt, maxOutputTokens: 200);
            $json = preg_replace('/^```json\s*|```$/m', '', $response);
            $tags = json_decode(trim($json), true);

            if (!is_array($tags)) {
                $this->logger->warning('Invalid tags JSON response', ['response' => $response]);
                return [];
            }

            // Validate and sanitize tags
            $sanitizedTags = array_filter(
                array_map(fn($tag) => is_string($tag) ? strtolower(trim($tag)) : null, $tags),
                fn($tag) => !empty($tag) && strlen($tag) <= 30
            );

            return array_slice(array_values($sanitizedTags), 0, 5);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate tags', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function extractCleanText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        try {
            // Use DOMDocument for better HTML parsing
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_ERR_NONE | LIBXML_ERR_ERROR);
            
            // Remove script and style elements
            foreach ($dom->getElementsByTagName('script') as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($dom->getElementsByTagName('style') as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($dom->getElementsByTagName('noscript') as $node) {
                $node->parentNode?->removeChild($node);
            }
            
            // Get text content
            $text = $dom->textContent ?? '';
            
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Remove excessive whitespace
            $text = preg_replace('/[\r\n\t]+/', "\n", $text);
            $text = preg_replace('/[ \t]+/', ' ', $text);
            
            // Split into lines and filter empty ones
            $lines = array_filter(
                array_map('trim', explode("\n", $text)),
                fn($line) => strlen($line) > 0
            );
            
            $text = implode("\n\n", $lines);
            
            // Truncate to max length
            return mb_substr(trim($text), 0, self::MAX_TEXT_LENGTH);
        } catch (\Throwable $e) {
            $this->logger->warning('Error extracting text from HTML, falling back to simple method', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to simple method
            $html = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $html);
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            return mb_substr(trim($text), 0, self::MAX_TEXT_LENGTH);
        }
    }
}
