<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SupabaseClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SupabaseClientTest extends TestCase
{
    public function testSelectSendsCorrectRequest(): void
    {
        $mockResponse = new MockResponse(json_encode([
            ['section' => 'test', 'key' => 'foo', 'value' => 'bar'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new SupabaseClient($httpClient, 'https://example.supabase.co', 'test-key');

        $result = $client->select('project_state', ['select' => 'section,key,value']);

        $this->assertCount(1, $result);
        $this->assertSame('test', $result[0]['section']);
        $this->assertSame('foo', $result[0]['key']);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/rest/v1/project_state', $mockResponse->getRequestUrl());
    }

    public function testInsertSendsPostRequest(): void
    {
        $mockResponse = new MockResponse(json_encode([
            ['id' => 'uuid-123', 'section' => 'test', 'key' => 'new'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new SupabaseClient($httpClient, 'https://example.supabase.co', 'test-key');

        $result = $client->insert('project_state', [
            'section' => 'test',
            'key' => 'new',
            'value' => 'val',
        ]);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('uuid-123', $result[0]['id']);
    }

    public function testDeleteSendsDeleteRequest(): void
    {
        $mockResponse = new MockResponse('');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new SupabaseClient($httpClient, 'https://example.supabase.co', 'test-key');

        $client->delete('project_state', 'id=eq.uuid-123');

        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('id=eq.uuid-123', $mockResponse->getRequestUrl());
    }

    public function testAuthHeadersAreSent(): void
    {
        $mockResponse = new MockResponse('[]');
        $httpClient = new MockHttpClient($mockResponse);
        $client = new SupabaseClient($httpClient, 'https://example.supabase.co', 'my-secret-key');

        $client->select('posts');

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        $headerString = implode("\n", $headers);
        $this->assertStringContainsString('apikey: my-secret-key', $headerString);
        $this->assertStringContainsString('Authorization: Bearer my-secret-key', $headerString);
    }
}
