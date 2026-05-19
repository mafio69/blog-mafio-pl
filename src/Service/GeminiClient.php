<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiClient
{
    private const MODEL = 'gemini-3.1-flash-lite';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1/models/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $googleApiKey,
    ) {}

    public function generateContent(string $prompt): string
    {
        $url = self::API_URL . self::MODEL . ':generateContent?key=' . $this->googleApiKey;

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 4096,
                ]
            ]
        ]);

        $data = $response->toArray();

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Failed to generate summary.';
    }
}
