<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminHostingViewTest extends TestCase
{
    public function testViewFileDoesNotConstructDatabase(): void
    {
        // Note: this test ensures the app/Views/admin/hosting/index.php view (app dashboard)
        // does not construct Database directly. The public landing page is separate.
        $path = dirname(__DIR__, 2) . '/app/Views/admin/hosting/index.php';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringNotContainsString('new App\\Helpers\\Database', $content, 'View must not directly construct Database');
        $this->assertDoesNotMatchRegularExpression('/new\s+Database\s*\(/', $content, 'View must not call new Database(...)');
    }

    public function testPublicLandingPageExists(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/public/landing.php';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        // No secrets or env values exposed
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('phpversion', strtolower($content));
        $this->assertStringNotContainsString('PHP_VERSION', $content);
        // Required structure
        $this->assertStringContainsString('FreeHost Manager', $content);
        $this->assertStringContainsString('href="/register"', $content);
        $this->assertStringContainsString('href="/login"', $content);
    }

    public function testPublicLandingCssExists(): void
    {
        $path = dirname(__DIR__, 2) . '/public/assets/css/landing.css';
        $this->assertFileExists($path);
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
