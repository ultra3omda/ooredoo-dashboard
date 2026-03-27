<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SubstorePluxeeConfigTest extends TestCase
{
    public function test_config_file_defines_pluxee_distributor_ids(): void
    {
        $path = dirname(__DIR__, 2) . '/config/substore.php';
        $this->assertFileExists($path);
        $config = require $path;
        $this->assertArrayHasKey('pluxee_distributor_store_ids', $config);
        $this->assertIsArray($config['pluxee_distributor_store_ids']);
        $this->assertContains(61, $config['pluxee_distributor_store_ids']);
        $this->assertArrayHasKey('pluxee_fallback_employer_sub_store', $config);
        $this->assertArrayHasKey('pluxee_match_json_stores', $config);
    }
}
