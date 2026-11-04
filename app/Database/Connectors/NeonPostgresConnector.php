<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $endpointId = $config['neon_endpoint_id'] ?? null;
        $dsn = parent::getDsn($config);

        return $endpointId
            ? $dsn.';options=endpoint='.$endpointId
            : $dsn;
    }
}
