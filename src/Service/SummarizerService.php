<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SummarizerService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private GeminiClient $geminiClient,
    ) {}

    public function summarizeUrl(string $url): string
    {
        // 1. Fetch content
        $response = $this->httpClient->request('GET', $url);
        $html = $response->getContent();

        // 2. Simple text extraction (remove scripts, styles, and tags)
        $text = $this->extractCleanText($html);

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
        return $this->geminiClient->generateContent($prompt);
    }

    public function generateTitle(string $content): string
    {
        $text = mb_substr($content, 0, 1500);

        $prompt = <<<PROMPT
Na podstawie poniższego artykułu napisz JEDEN krótki, chwytliwy tytuł po polsku (max 80 znaków).
Tytuł ma być konkretny, informacyjny i przyciągający uwagę.
NIE dodawaj cudzysłowów, kropki na końcu ani żadnego formatowania. Odpowiedz TYLKO tytułem.

Artykuł:
$text
PROMPT;

        $title = trim($this->geminiClient->generateContent($prompt));
        $title = trim($title, '"\'.');

        return mb_strlen($title) > 100 ? mb_substr($title, 0, 100) : $title;
    }

    public function generateTags(string $content): array
    {
        $text = mb_substr($content, 0, 2000);

        $prompt = <<<PROMPT
Na podstawie poniższego artykułu wygeneruj 2-5 tagów (krótkich, 1-2 słowa, po angielsku, lowercase).
Tagi powinny opisywać technologie, tematy i kategorie artykułu.
Przykłady dobrych tagów: php, docker, ai, symfony, devops, security, linux, kubernetes, laravel, javascript, python, cloud, database, performance, testing

Odpowiedz WYŁĄCZNIE tablicą JSON, np: ["php", "docker", "security"]

Artykuł:
$text
PROMPT;

        $response = $this->geminiClient->generateContent($prompt);
        $json = preg_replace('/^```json\s*|```$/m', '', $response);
        $tags = json_decode(trim($json), true);

        return is_array($tags) ? array_slice($tags, 0, 5) : [];
    }

    private function extractCleanText(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $html);
        
        // Strip all tags
        $text = strip_tags($html);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Truncate to avoid context window issues (though 1.5/3.1 are huge, let's keep it reasonable)
        return mb_substr(trim($text), 0, 15000);
    }
}
