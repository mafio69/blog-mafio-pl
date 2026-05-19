<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AggregatorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private FeedService $feedService,
        private GeminiClient $geminiClient,
        private SummarizerService $summarizer,
        private PostService $postService
    ) {}

    public function run(): array
    {
        $feeds = $this->feedService->getAllActive();
        $processedCount = 0;
        $allResults = [];

        foreach ($feeds as $feed) {
            $items = $this->fetchRssItems($feed['url']);
            
            // Triage: Ask Gemini which headlines are worth summarizing
            $selectedLinks = $this->triageHeadlines($items);

            foreach ($selectedLinks as $link) {
                // Check if already exists
                if ($this->postService->findOneBySlug($this->slugify($link['title']))) {
                    continue;
                }

                try {
                    $summary = $this->summarizer->summarizeUrl($link['url']);
                    
                    $this->postService->create([
                        'title' => $link['title'],
                        'content' => $summary, // For now, summary is the content
                        'summary' => mb_substr($summary, 0, 300) . '...',
                        'status' => 'draft',
                        'auto_generated' => true,
                        'source_urls' => [$link['url']],
                        'tags' => [$feed['category']],
                    ]);
                    
                    $processedCount++;
                    $allResults[] = $link['title'];
                } catch (\Exception $e) {
                    // Log error and continue
                }
            }

            $this->feedService->updateLastFetched($feed['id']);
        }

        return [
            'processed' => $processedCount,
            'titles' => $allResults
        ];
    }

    private function fetchRssItems(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);
        $xml = new \SimpleXMLElement($response->getContent());
        
        $items = [];
        // Handle both RSS 2.0 and Atom
        $entries = $xml->channel->item ?? $xml->entry;

        foreach ($entries as $item) {
            $items[] = [
                'title' => (string) ($item->title),
                'url' => (string) ($item->link['href'] ?? $item->link),
            ];
            if (count($items) >= 10) break; // Limit per feed to save tokens
        }

        return $items;
    }

    private function triageHeadlines(array $items): array
    {
        if (empty($items)) return [];

        $headlines = "";
        foreach ($items as $index => $item) {
            $headlines .= "$index. {$item['title']}\n";
        }

        $prompt = <<<PROMPT
Działasz jako kurator treści dla inteligentnego bloga techniczno-cywilizacyjnego. 
Z poniższej listy nagłówków wybierz maksymalnie 2, które są NAPRAWDĘ istotne.
Kryteria wyboru:
1. Wielkie wydarzenia geopolityczne lub społeczne w świecie zachodnim (USA, UE, UK, Kanada, Australia, NZ) lub u kluczowych sojuszników (Japonia, Korea Płd.).
2. Przełomowe nowości w świecie PHP lub technologii (nie błahe poprawki).
3. Fascynujące ciekawostki dla nerdów/programistów.

Zignoruj: newsy lokalne, błahe afery polityczne, powtarzalne newsy o giełdzie.

Odpowiedz WYŁĄCZNIE w formacie JSON (tablica indeksów), np: [0, 3]

Nagłówki:
$headlines
PROMPT;

        $response = $this->geminiClient->generateContent($prompt);
        
        // Clean markdown JSON if present
        $json = preg_replace('/^```json\s*|```$/m', '', $response);
        $indices = json_decode(trim($json), true) ?? [];

        $selected = [];
        foreach ($indices as $index) {
            if (isset($items[$index])) {
                $selected[] = $items[$index];
            }
        }

        return $selected;
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }
}
