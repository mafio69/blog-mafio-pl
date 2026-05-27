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

    public function run(int $maxFeeds = null, int $maxArticlesPerFeed = 10): array
    {
        $feeds = $this->feedService->getAllActive();
        
        if ($maxFeeds !== null && count($feeds) > $maxFeeds) {
            $feeds = array_slice($feeds, 0, $maxFeeds);
        }
        
        $processedCount = 0;
        $allResults = [];
        $errorCount = 0;

        foreach ($feeds as $feed) {
            try {
                $items = $this->fetchRssItems($feed['url'], $maxArticlesPerFeed);
                
                if (empty($items)) {
                    $this->logger->info('No items fetched from feed', ['feed_url' => $feed['url']]);
                    continue;
                }
                
                $selectedLinks = $this->triageHeadlines($items);

                foreach ($selectedLinks as $link) {
                    if (!$this->isValidUrl($link['url'])) {
                        $this->logger->warning('Invalid URL detected, skipping', ['url' => $link['url']]);
                        continue;
                    }

                    if ($this->isDuplicate($link['url'])) {
                        $this->logger->info('Duplicate article detected, skipping', [
                            'url' => $link['url'],
                            'title' => $link['title'],
                        ]);
                        continue;
                    }

                    try {
                        $summary = $this->summarizer->summarizeUrl($link['url']);
                        
                        if (empty(trim($summary))) {
                            $this->logger->warning('Empty summary generated, skipping', ['url' => $link['url']]);
                            continue;
                        }
                        
                        $title = $this->summarizer->generateTitle($summary);
                        $tags = $this->summarizer->generateTags($summary);
                        $tags[] = $feed['category'];

                        $this->postService->create([
                            'title' => $title,
                            'content' => $summary,
                            'summary' => mb_substr($summary, 0, 300) . '...',
                            'status' => 'draft',
                            'auto_generated' => true,
                            'source_urls' => [$link['url']],
                            'tags' => array_unique($tags),
                        ]);

                        $processedCount++;
                        $allResults[] = $title;
                        $this->logger->info('Successfully processed article', [
                            'title' => $title,
                            'url' => $link['url'],
                        ]);
                    } catch (\Throwable $e) {
                        $errorCount++;
                        $this->logger->error('Error processing article', [
                            'feed_url' => $feed['url'],
                            'article_title' => $link['title'],
                            'article_url' => $link['url'],
                            'error' => $e->getMessage(),
                            'exception' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                $this->feedService->updateLastFetched($feed['id']);
            } catch (\Throwable $e) {
                $this->logger->error('Error processing feed', [
                    'feed_id' => $feed['id'],
                    'feed_name' => $feed['name'] ?? 'unknown',
                    'feed_url' => $feed['url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Aggregation completed', [
            'feeds_processed' => count($feeds),
            'articles_created' => $processedCount,
            'errors' => $errorCount,
        ]);

        return [
            'processed' => $processedCount,
            'titles' => $allResults,
            'errors' => $errorCount,
            'feeds_count' => count($feeds),
        ];
    }

    private function fetchRssItems(string $url, int $limit = 10): array
    {
        if (!$this->isValidUrl($url)) {
            $this->logger->warning('Invalid RSS feed URL', ['url' => $url]);
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; BlogAggregator/1.0)',
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                ],
            ]);
            
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error('RSS feed returned error status', [
                    'url' => $url,
                    'status' => $statusCode,
                ]);
                return [];
            }

            $content = $response->getContent();
            
            libxml_use_internal_errors(true);
            $xml = new \SimpleXMLElement($content);
            $errors = libxml_get_errors();
            
            if (!empty($errors)) {
                $this->logger->warning('XML parsing warnings', [
                    'url' => $url,
                    'errors' => array_map(fn($e) => $e->message, $errors),
                ]);
                libxml_clear_errors();
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching RSS feed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return [];
        }

        $items = [];
        $entries = $xml->channel->item ?? $xml->entry;

        if (!$entries) {
            $this->logger->warning('No items found in RSS feed', ['url' => $url]);
            return [];
        }

        foreach ($entries as $item) {
            $title = (string) $item->title;
            $link = (string) ($item->link['href'] ?? $item->link);
            
            if (empty($title) || empty($link)) {
                $this->logger->debug('Skipping RSS item with missing title or link', [
                    'feed_url' => $url,
                    'title' => $title,
                    'link' => $link,
                ]);
                continue;
            }
            
            $items[] = [
                'title' => $title,
                'url' => $link,
            ];
            
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
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

        if (empty($parsed['host'])) {
            return false;
        }

        return true;
    }

    private function isDuplicate(string $url): bool
    {
        $allPosts = $this->postService->findAll();
        
        foreach ($allPosts as $post) {
            $sourceUrls = $post['source_urls'] ?? [];
            if (in_array($url, $sourceUrls, true)) {
                return true;
            }
        }

        return false;
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
