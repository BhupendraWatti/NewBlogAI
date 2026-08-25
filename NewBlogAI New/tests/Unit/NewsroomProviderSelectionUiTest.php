<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NewsroomProviderSelectionUiTest extends TestCase
{
    public function test_discovery_uses_auto_failover_instead_of_an_invisible_groq_default(): void
    {
        $scripts = file_get_contents(dirname(__DIR__, 2).'/resources/views/partials/scripts.blade.php');

        $this->assertStringContainsString("const discoveryProvider = 'auto';", $scripts);
        $this->assertStringNotContainsString("document.getElementById('gen-discovery-provider')?.value || 'groq'", $scripts);
    }
}
