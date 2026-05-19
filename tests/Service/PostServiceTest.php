<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PostService;
use App\Service\SummarizerService;
use App\Service\SupabaseClient;
use PHPUnit\Framework\TestCase;

class PostServiceTest extends TestCase
{
    private function createService(SupabaseClient $supabase): PostService
    {
        $summarizer = $this->createStub(SummarizerService::class);
        return new PostService($supabase, $summarizer);
    }

    public function testFindAll(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('posts', $this->anything())
            ->willReturn([['title' => 'Test Post']]);

        $result = $this->createService($supabase)->findAll();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Post', $result[0]['title']);
    }

    public function testFindOneBySlug(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('posts', $this->callback(fn($params) => $params['slug'] === 'eq.test-post'))
            ->willReturn([['title' => 'Test Post', 'slug' => 'test-post']]);

        $result = $this->createService($supabase)->findOneBySlug('test-post');

        $this->assertNotNull($result);
        $this->assertEquals('Test Post', $result['title']);
    }

    public function testCreateGeneratesSlug(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('insert')
            ->with('posts', $this->callback(fn($data) => $data['slug'] === 'hello-world'))
            ->willReturn(['id' => '1', 'title' => 'Hello World', 'slug' => 'hello-world']);

        $this->createService($supabase)->create(['title' => 'Hello World']);
    }

    public function testUpdate(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('update')
            ->with('posts', 'id=eq.1', ['title' => 'Updated Title'])
            ->willReturn(['id' => '1', 'title' => 'Updated Title']);

        $this->createService($supabase)->update('1', ['title' => 'Updated Title']);
    }

    public function testDelete(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('delete')
            ->with('posts', 'id=eq.1');

        $this->createService($supabase)->delete('1');
    }
}
