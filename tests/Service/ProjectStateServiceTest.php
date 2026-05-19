<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProjectStateService;
use App\Service\SupabaseClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectStateService.
 */
class ProjectStateServiceTest extends TestCase
{
    /**
     * Mock data with multiple sections for testing grouping functionality.
     */
    private const MOCK_DATA_MULTIPLE_SECTIONS = [
        [
            'section' => 'infrastructure',
            'key' => 'php',
            'value' => '8.4',
            'encrypted' => false,
            'updated_at' => '2026-05-17',
        ],
        [
            'section' => 'infrastructure',
            'key' => 'os',
            'value' => 'Ubuntu',
            'encrypted' => false,
            'updated_at' => '2026-05-17',
        ],
        [
            'section' => 'security',
            'key' => 'password',
            'value' => 'enc123',
            'encrypted' => true,
            'updated_at' => '2026-05-17',
        ],
    ];

    /**
     * Test grouping by sections with multiple sections.
     */
    public function testGetAllGroupedBySectionsWithMultipleSections(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('project_state', $this->anything())
            ->willReturn(self::MOCK_DATA_MULTIPLE_SECTIONS);

        $service = new ProjectStateService($supabase);
        $result = $service->getAllGroupedBySections();

        $this->assertArrayHasKey('infrastructure', $result);
        $this->assertArrayHasKey('security', $result);
        $this->assertCount(2, $result['infrastructure']);
        $this->assertCount(1, $result['security']);
        $this->assertTrue($result['security'][0]['encrypted']);
    }

    /**
     * Test grouping by sections with an empty result.
     */
    public function testGetAllGroupedBySectionsWithEmptyResult(): void
    {
        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('project_state', $this->anything())
            ->willReturn([]);

        $service = new ProjectStateService($supabase);
        $result = $service->getAllGroupedBySections();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test grouping by sections with a single section.
     */
    public function testGetAllGroupedBySectionsWithSingleSection(): void
    {
        $singleSectionData = [
            [
                'section' => 'infrastructure',
                'key' => 'php',
                'value' => '8.4',
                'encrypted' => false,
                'updated_at' => '2026-05-17',
            ],
        ];

        $supabase = $this->createMock(SupabaseClient::class);
        $supabase->expects($this->once())
            ->method('select')
            ->with('project_state', $this->anything())
            ->willReturn($singleSectionData);

        $service = new ProjectStateService($supabase);
        $result = $service->getAllGroupedBySections();

        $this->assertArrayHasKey('infrastructure', $result);
        $this->assertCount(1, $result['infrastructure']);
        $this->assertArrayNotHasKey('security', $result);
    }
}
