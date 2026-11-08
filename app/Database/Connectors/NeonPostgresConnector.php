<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $endpointId = $config['neon_endpoint_id'] ?? null;
        if (! is_string($endpointId) || trim($endpointId) === '') {
            $host = $config['host'] ?? null;
            if (is_string($host) && str_ends_with(strtolower($host), '.neon.tech')) {
                $hostname = strtolower(explode('.', $host, 2)[0]);
                $endpointId = preg_replace('/-pooler$/', '', $hostname);
                if (! is_string($endpointId) || preg_match('/^ep-[a-z0-9]+(?:-[a-z0-9]+)*$/', $endpointId) !== 1) {
                    $endpointId = null;
                }
            }
        }

        $dsn = parent::getDsn($config);

        return $endpointId
            ? $dsn.';options=endpoint='.$endpointId
            : $dsn;
    }
}
