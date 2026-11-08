<?php

namespace Tests\Unit;

use App\Database\Connectors\NeonPostgresConnector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NeonPostgresConnectorTest extends TestCase
{
    public function test_it_derives_the_endpoint_id_from_a_pooled_neon_host(): void
    {
        $dsn = $this->dsnForHost('ep-empty-fire-azjpo2ac-pooler.c-3.ap-southeast-1.aws.neon.tech');

        $this->assertStringContainsString(';options=endpoint=ep-empty-fire-azjpo2ac', $dsn);
    }

    public function test_it_derives_the_endpoint_id_from_a_direct_neon_host(): void
    {
        $dsn = $this->dsnForHost('ep-empty-fire-azjpo2ac.c-3.ap-southeast-1.aws.neon.tech');

        $this->assertStringContainsString(';options=endpoint=ep-empty-fire-azjpo2ac', $dsn);
    }

    public function test_it_does_not_add_an_endpoint_id_for_non_neon_hosts(): void
    {
        $dsn = $this->dsnForHost('db.example.test');

        $this->assertStringNotContainsString('options=endpoint=', $dsn);
    }

    private function dsnForHost(string $host): string
    {
        $connector = new NeonPostgresConnector;
        $method = new ReflectionMethod($connector, 'getDsn');

        return $method->invoke($connector, [
            'host' => $host,
            'database' => 'neondb',
            'port' => 5432,
            'charset' => 'utf8',
            'sslmode' => 'require',
        ]);
    }
}
