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
Przeanalizuj poniższy artykuł techniczny i napisz jego streszczenie w języku polskim.
Streszczenie powinno:
- Składać się z dokładnie 2-3 akapitów.
- Być napisane językiem profesjonalnym, ale przystępnym dla programisty.
- Skupiać się na najważniejszych wnioskach technicznych i nowościach.

Artykuł:
$text
PROMPT;

        // 4. Call Gemini
        return $this->geminiClient->generateContent($prompt);
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
