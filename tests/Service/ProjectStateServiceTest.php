<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProjectStateService;
use App\Service\SupabaseClient;
use PHPUnit\Framework\TestCase;

class ProjectStateServiceTest extends TestCase
{
    public function testGetAllGroupedBySections(): void
    {
        $mockData = [
            ['section' => 'infrastructure', 'key' => 'php', 'value' => '8.4', 'encrypted' => false, 'updated_at' => '2026-05-17'],
            ['section' => 'infrastructure', 'key' => 'os', 'value' => 'Ubuntu', 'encrypted' => false, 'updated_at' => '2026-05-17'],
            ['section' => 'security', 'key' => 'password', 'value' => 'enc123', 'encrypted' => true, 'updated_at' => '2026-05-17'],
        ];

        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('project_state', $this->anything())
            ->willReturn($mockData);

        $service = new ProjectStateService($supabase);
        $result = $service->getAllGroupedBySections();

        $this->assertArrayHasKey('infrastructure', $result);
        $this->assertArrayHasKey('security', $result);
        $this->assertCount(2, $result['infrastructure']);
        $this->assertCount(1, $result['security']);
        $this->assertTrue($result['security'][0]['encrypted']);
    }
}
