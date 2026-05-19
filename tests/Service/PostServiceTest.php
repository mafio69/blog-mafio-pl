<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PostService;
use App\Service\SupabaseClient;
use PHPUnit\Framework\TestCase;

class PostServiceTest extends TestCase
{
    public function testFindAll(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('posts', $this->anything())
            ->willReturn([['title' => 'Test Post']]);

        $service = new PostService($supabase);
        $result = $service->findAll();

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

        $service = new PostService($supabase);
        $result = $service->findOneBySlug('test-post');

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

        $service = new PostService($supabase);
        $service->create(['title' => 'Hello World']);
    }

    public function testUpdate(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('update')
            ->with('posts', 'id=eq.1', ['title' => 'Updated Title'])
            ->willReturn(['id' => '1', 'title' => 'Updated Title']);

        $service = new PostService($supabase);
        $service->update('1', ['title' => 'Updated Title']);
    }

    public function testDelete(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('delete')
            ->with('posts', 'id=eq.1');

        $service = new PostService($supabase);
        $service->delete('1');
    }
}
