<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SupabaseClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $supabaseUrl,
        private string $serviceRoleKey,
    ) {}

    public function select(string $table, array $params = []): array
    {
        return $this->request('GET', $table, query: $params);
    }

    public function insert(string $table, array $data): array
    {
        return $this->request('POST', $table, json: $data, headers: [
            'Prefer' => 'return=representation',
        ]);
    }

    public function update(string $table, string $filter, array $data): array
    {
        return $this->request('PATCH', $table . '?' . $filter, json: $data, headers: [
            'Prefer' => 'return=representation',
        ]);
    }

    public function delete(string $table, string $filter): void
    {
        $this->request('DELETE', $table . '?' . $filter);
    }

    private function request(string $method, string $path, array $query = [], array $json = [], array $headers = []): array
    {
        $options = [
            'headers' => array_merge([
                'apikey' => $this->serviceRoleKey,
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'Content-Type' => 'application/json',
            ], $headers),
        ];

        if ($query) {
            $options['query'] = $query;
        }
        if ($json) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $this->supabaseUrl . '/rest/v1/' . $path, $options);

        $content = $response->getContent(false);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $errorData = $content ? json_decode($content, true) : [];
            $message = $errorData['message'] ?? $errorData['error'] ?? "HTTP $statusCode";
            throw new \RuntimeException("Supabase API error: $message");
        }

        return $content ? json_decode($content, true) ?? [] : [];
    }
}
