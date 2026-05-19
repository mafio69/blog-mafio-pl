<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AggregatorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private FeedService $feedService,
        private GeminiClient $geminiClient,
        private SummarizerService $summarizer,
        private PostService $postService,
        private LoggerInterface $logger,
    ) {}

    public function run(): array
    {
        $feeds = $this->feedService->getAllActive();
        $processedCount = 0;
        $allResults = [];

        foreach ($feeds as $feed) {
            $items = $this->fetchRssItems($feed['url']);
            $selectedLinks = $this->triageHeadlines($items);

            foreach ($selectedLinks as $link) {
                if ($this->postService->findOneBySlug($this->postService->generateSlug($link['title']))) {
                    continue;
                }

                try {
                    $summary = $this->summarizer->summarizeUrl($link['url']);

                    $this->postService->create([
                        'title' => $link['title'],
                        'content' => $summary,
                        'summary' => mb_substr($summary, 0, 300) . '...',
                        'status' => 'draft',
                        'auto_generated' => true,
                        'source_urls' => [$link['url']],
                        'tags' => [$feed['category']],
                    ]);

                    $processedCount++;
                    $allResults[] = $link['title'];
                } catch (\Throwable $e) {
                    $this->logger->error('Error processing article', [
                        'feed_url' => $feed['url'],
                        'article_title' => $link['title'],
                        'article_url' => $link['url'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->feedService->updateLastFetched($feed['id']);
        }

        return ['processed' => $processedCount, 'titles' => $allResults];
    }

    private function fetchRssItems(string $url): array
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $xml = new \SimpleXMLElement($response->getContent());
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching RSS feed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $items = [];
        $entries = $xml->channel->item ?? $xml->entry;

        foreach ($entries as $item) {
            $items[] = [
                'title' => (string) $item->title,
                'url' => (string) ($item->link['href'] ?? $item->link),
            ];
            if (count($items) >= 10) {
                break;
            }
        }

        return $items;
    }

    private function triageHeadlines(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $headlines = '';
        foreach ($items as $index => $item) {
            $headlines .= "$index. {$item['title']}\n";
        }

        $prompt = <<<PROMPT
            Działasz jako kurator treści dla inteligentnego bloga techniczno-cywilizacyjnego.
            Z poniższej listy nagłówków wybierz maksymalnie 3, które są NAPRAWDĘ istotne.
            Kryteria wyboru:
            1. Wielkie wydarzenia geopolityczne lub społeczne w świecie zachodnim (USA, UE, UK, Kanada, Australia, NZ) lub u kluczowych sojuszników (Japonia, Korea Płd.).
            2. Przełomowe nowości w świecie PHP lub technologii (nie błahe poprawki).
            3. Fascynujące ciekawostki dla nerdów/programistów.
            4. Też wirale by nerd wiedział co się dzieje w świecie i mógł się pochwalić przed znajomymi.

            Zignoruj: newsy lokalne, błahe afery polityczne, powtarzalne newsy o giełdzie.

            Odpowiedz WYŁĄCZNIE w formacie JSON (tablica indeksów), np: [0, 3]

            Nagłówki:
            $headlines
            PROMPT;

        $response = $this->geminiClient->generateContent($prompt);
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
}
