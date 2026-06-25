#!/usr/bin/env php
<?php
/**
 * MCP Server dla blog.mafio.pl
 *
 * Uruchomienie: php bin/mcp-server.php
 * Endpoint: http://127.0.0.1:8081/mcp (streamable HTTP)
 *
 * Działa jako persistent process (ReactPHP), nie podlega max_execution_time
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Tool;
use PhpMcp\Server\Http\StreamableHttpTransport;

// Konfiguracja z .env / .env.local
$dotenv = new Symfony\Component\Dotenv\Dotenv();
$dotenv->bootEnv(__DIR__ . '/../.env');

$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$supabaseKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? '';

if (empty($supabaseUrl) || empty($supabaseKey)) {
    echo "Błąd: Brak konfiguracji Supabase w .env\n";
    exit(1);
}

// Inicjalizacja klienta Supabase
$httpClient = Symfony\Component\HttpClient\HttpClient::create();

// Funkcja pomocnicza do zapytań Supabase
function supabaseRequest($client, $url, $key, $method, $path, $data = null) {
    $options = [
        'headers' => [
            'apikey' => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
        ],
    ];

    if ($data !== null) {
        $options['json'] = $data;
    }

    $response = $client->request($method, $url . '/rest/v1/' . $path, $options);
    return $response->toArray();
}

// TWORZENIE SERWERA MCP
$server = new Server([
    'name' => 'blog-mafio-pl',
    'version' => '1.0.0',
]);

// ============================================================
// TOOL: list_posts - Lista artykułów
// ============================================================
$server->addTool(new Tool(
    'list_posts',
    'Lista artykułów z bloga z opcjonalnym filtrowaniem',
    [
        'status' => [
            'type' => 'string',
            'description' => 'Filtruj po statusie: draft, published, lub all',
            'enum' => ['draft', 'published', 'all'],
            'default' => 'published',
        ],
        'tag' => [
            'type' => 'string',
            'description' => 'Filtruj po tagu (opcjonalne)',
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Limit wyników (max 50)',
            'default' => 10,
            'maximum' => 50,
        ],
    ],
    function ($params) use ($httpClient, $supabaseUrl, $supabaseKey) {
        $status = $params['status'] ?? 'published';
        $tag = $params['tag'] ?? null;
        $limit = min($params['limit'] ?? 10, 50);

        // Buduj query
        $query = "posts?select=*&order=created_at.desc&limit=$limit";

        if ($status !== 'all') {
            $query .= "&status=eq.$status";
        }

        if ($tag) {
            $query .= "&tags=cs.{" . urlencode($tag) . "}";
        }

        try {
            $posts = supabaseRequest($httpClient, $supabaseUrl, $supabaseKey, 'GET', $query);

            // Formatuj wyniki
            $formatted = array_map(static function($post) {
                return [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'status' => $post['status'],
                    'tags' => $post['tags'] ?? [],
                    'created_at' => $post['created_at'],
                    'summary' => substr($post['summary'] ?? $post['content'], 0, 200) . '...',
                ];
            }, $posts);

            return [
                'count' => count($formatted),
                'posts' => $formatted,
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
));

// ============================================================
// TOOL: create_post - Tworzenie nowego artykułu
// ============================================================
$server->addTool(new Tool(
    'create_post',
    'Tworzy nowy artykuł na blogu',
    [
        'title' => [
            'type' => 'string',
            'description' => 'Tytuł artykułu',
        ],
        'content' => [
            'type' => 'string',
            'description' => 'Treść artykułu w markdown',
        ],
        'tags' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Tagi artykułu (np. ["php", "laravel"])',
        ],
        'status' => [
            'type' => 'string',
            'enum' => ['draft', 'published'],
            'default' => 'draft',
            'description' => 'Status artykułu',
        ],
        'source_urls' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'URL-e źródłowe (opcjonalne)',
        ],
    ],
    function ($params) use ($httpClient, $supabaseUrl, $supabaseKey) {
        $title = $params['title'];
        $content = $params['content'];

        // Generuj slug z tytułu
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        // Generuj summary (pierwsze 500 znaków)
        $summary = substr(strip_tags($content), 0, 500);

        $data = [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'summary' => $summary,
            'tags' => $params['tags'] ?? [],
            'status' => $params['status'] ?? 'draft',
            'source_urls' => $params['source_urls'] ?? [],
            'auto_generated' => false,
        ];

        if ($data['status'] === 'published') {
            $data['published_at'] = date('c');
        }

        try {
            $result = supabaseRequest($httpClient, $supabaseUrl, $supabaseKey, 'POST', 'posts', $data);
            return [
                'success' => true,
                'message' => 'Artykuł utworzony',
                'post' => [
                    'id' => $result[0]['id'] ?? 'unknown',
                    'title' => $title,
                    'slug' => $slug,
                    'status' => $data['status'],
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
));

// ============================================================
// TOOL: fetch_rss - Ręczne pobieranie feedów
// ============================================================
$server->addTool(new Tool(
    'fetch_rss',
    'Ręczne uruchomienie pobierania artykułów z RSS feedów',
    [
        'feeds' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Lista URL-i feedów do pobrania (puste = wszystkie aktywne)',
        ],
    ],
    function ($params) use ($httpClient, $supabaseUrl, $supabaseKey) {
        // Pobierz aktywne feedy
        try {
            $feeds = supabaseRequest(
                $httpClient,
                $supabaseUrl,
                $supabaseKey,
                'GET',
                'feeds?select=*&active=eq.true'
            );

            $results = [];

            foreach ($feeds as $feed) {
                // Tutaj logika pobierania RSS i zapisywania artykułów
                // Uproszczona wersja - w prawdziwej implementacji
                // użyłbyś FeedService z Twojej aplikacji

                $results[] = [
                    'feed' => $feed['name'],
                    'url' => $feed['url'],
                    'status' => 'fetch_scheduled',
                ];
            }

            return [
                'success' => true,
                'message' => 'Pobieranie zaplanowane',
                'feeds_processed' => count($results),
                'details' => $results,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
));

// ============================================================
// TOOL: get_post - Pobranie szczegółów artykułu
// ============================================================
$server->addTool(new Tool(
    'get_post',
    'Pobiera pełne szczegóły artykułu po ID lub slug',
    [
        'id' => [
            'type' => 'string',
            'description' => 'UUID artykułu',
        ],
        'slug' => [
            'type' => 'string',
            'description' => 'Slug artykułu (alternatywa dla id)',
        ],
    ],
    function ($params) use ($httpClient, $supabaseUrl, $supabaseKey) {
        if (!empty($params['id'])) {
            $query = "posts?id=eq." . urlencode($params['id']);
        } elseif (!empty($params['slug'])) {
            $query = "posts?slug=eq." . urlencode($params['slug']);
        } else {
            return ['error' => 'Wymagane id lub slug'];
        }

        try {
            $posts = supabaseRequest($httpClient, $supabaseUrl, $supabaseKey, 'GET', $query);

            if (empty($posts)) {
                return ['error' => 'Artykuł nie znaleziony'];
            }

            return [
                'success' => true,
                'post' => $posts[0],
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
));

// ============================================================
// URUCHOMIENIE SERWERA
// ============================================================

$port = $_ENV['MCP_SERVER_PORT'] ?? '8081';
$host = $_ENV['MCP_SERVER_HOST'] ?? '127.0.0.1';

echo "=== MCP Server blog.mafio.pl ===\n";
echo "Uruchamianie na http://$host:$port/mcp\n";
echo "Streamable HTTP transport (ReactPHP)\n";
echo "Naciśnij Ctrl+C by zatrzymać\n\n";

// Transport HTTP (streamable - dla MCP 2024-11-05)
$transport = new StreamableHttpTransport($host, (int)$port, '/mcp');
$server->connect($transport);

// Uruchom event loop (działa w nieskończoność)
$server->run();
