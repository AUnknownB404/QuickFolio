<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $endpointId = $config['neon_endpoint_id'] ?? null;
        $host = $config['host'] ?? null;
        if (is_string($host) && str_ends_with(strtolower($host), '.neon.tech')) {
            $hostname = strtolower(explode('.', $host, 2)[0]);
            if (preg_match('/^ep-[a-z0-9]+(?:-[a-z0-9]+)*$/', $hostname) === 1) {
                $endpointId = $hostname;
            }
        }

        $dsn = parent::getDsn($config);

        return $endpointId
            ? $dsn.';options=endpoint='.$endpointId
            : $dsn;
    }
}
