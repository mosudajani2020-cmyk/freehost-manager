<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminHostingViewTest extends TestCase
{
    public function testViewFileDoesNotConstructDatabase(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/admin/hosting/index.php';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringNotContainsString('new App\\Helpers\\Database', $content, 'View must not directly construct Database');
        $this->assertDoesNotMatchRegularExpression('/new\s+Database\s*\(/', $content, 'View must not call new Database(...)');
    }

    public function testViewFileUsesControllerProvidedData(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/admin/hosting/index.php';
        $content = file_get_contents($path);
        $this->assertStringContainsString("\$owners", $content, 'View should consume controller-provided $owners array');
    }

    public function testAdminHostingIndexProvidesAllRequiredFields(): void
    {
        // The view must reference the fields the spec requires:
        // username, owner, plan, status, storage, created
        $path = dirname(__DIR__, 2) . '/app/Views/admin/hosting/index.php';
        $content = file_get_contents($path);
        foreach (['ID','Username','Owner','Plan','Status','Storage','Created','View'] as $col) {
            $this->assertStringContainsString(">$col<", $content, "Column $col missing");
        }
    }
}
