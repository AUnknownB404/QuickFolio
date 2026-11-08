<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class RuntimeDiagnosticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('services.diagnostics.token');
        $providedToken = $request->header('X-Diagnostics-Token');

        abort_unless(
            is_string($token)
                && $token !== ''
                && is_string($providedToken)
                && hash_equals($token, $providedToken),
            404
        );

        $checks = [
            'runtime' => $this->check(function () {
                if (! filled(config('app.key'))) {
                    throw new \RuntimeException('APP_KEY is not configured.');
                }

                return [
                    'php' => PHP_VERSION,
                    'environment' => app()->environment(),
                    'debug_enabled' => (bool) config('app.debug'),
                    'app_key_configured' => true,
                ];
            }),
            'database' => $this->check(function () {
                DB::connection()->selectOne('select 1');

                return [
                    'connection' => DB::getDefaultConnection(),
                    'status' => 'connected',
                ];
            }),
            'tables' => $this->check(function () {
                $defaultConnection = DB::getDefaultConnection();
                $tables = [$defaultConnection => ['users']];

                if (config('session.driver') === 'database') {
                    $connection = config('session.connection') ?: $defaultConnection;
                    $tables[$connection][] = config('session.table', 'sessions');
                }

                if (config('cache.default') === 'database') {
                    $connection = config('cache.stores.database.connection') ?: $defaultConnection;
                    $cacheTable = config('cache.stores.database.table', 'cache');
                    $tables[$connection][] = $cacheTable;
                    $tables[$connection][] = config('cache.stores.database.lock_table') ?: 'cache_locks';
                }

                $available = [];

                foreach ($tables as $connection => $connectionTables) {
                    foreach (array_unique($connectionTables) as $table) {
                        $available[$connection.'.'.$table] = Schema::connection($connection)->hasTable($table);
                    }
                }

                $missing = array_keys(array_filter($available, fn (bool $exists) => ! $exists));
                if ($missing !== []) {
                    throw new \RuntimeException('Required database tables are missing: '.implode(', ', $missing));
                }

                return $available;
            }),
            'cache' => $this->check(function () {
                $key = 'runtime-diagnostic:'.Str::random(32);
                $cache = Cache::store();

                try {
                    $cache->put($key, 'ok', 30);
                    if ($cache->get($key) !== 'ok') {
                        throw new \RuntimeException('Cache read after write did not return the stored value.');
                    }

                    return [
                        'store' => config('cache.default'),
                        'round_trip' => true,
                    ];
                } finally {
                    $cache->forget($key);
                }
            }),
            'session' => $this->check(function () {
                $session = app('session')->driver();
                $handler = $session->getHandler();
                $id = Str::random(40);

                try {
                    $handler->open('', 'quickfolio-diagnostic');
                    $handler->write($id, 'diagnostic');
                    if ($handler->read($id) !== 'diagnostic') {
                        throw new \RuntimeException('Session read after write did not return the stored value.');
                    }

                    return [
                        'driver' => config('session.driver'),
                        'handler' => get_class($handler),
                        'round_trip' => true,
                    ];
                } finally {
                    $handler->destroy($id);
                    $handler->close();
                }
            }),
            'compiled_views' => $this->check(function () {
                $path = config('view.compiled');
                if (! is_string($path) || ! is_dir($path) || ! is_writable($path)) {
                    throw new \RuntimeException('The compiled view directory is missing or not writable.');
                }

                $probe = tempnam($path, 'quickfolio-diagnostic-');
                if ($probe === false) {
                    throw new \RuntimeException('Could not create a file in the compiled view directory.');
                }

                unlink($probe);

                return ['path' => $path, 'writable' => true];
            }),
            'vite_manifest' => $this->check(function () {
                $manifest = public_path('build/manifest.json');
                if (! is_file($manifest) || ! is_readable($manifest)) {
                    throw new \RuntimeException('The Vite build manifest is missing or unreadable.');
                }

                return [
                    'exists' => true,
                    'readable' => true,
                ];
            }),
        ];

        $failed = collect($checks)->contains(
            fn (array $check) => ($check['status'] ?? null) !== 'ok'
        );

        return response()->json([
            'status' => $failed ? 'failed' : 'ok',
            'checks' => $checks,
        ]);
    }

    private function check(callable $callback): array
    {
        try {
            return [
                'status' => 'ok',
                'details' => $callback(),
            ];
        } catch (Throwable $exception) {
            $message = preg_replace(
                [
                    '/postgres(?:ql)?:\/\/[^\s]+/i',
                    '/\bpassword\s*=\s*(\'[^\']*\'|"[^"]*"|[^\s;]+)/i',
                    '/\s+SQL:\s+.*$/is',
                ],
                ['[redacted database URL]', 'password=[redacted]', ' SQL: [redacted]'],
                $exception->getMessage()
            );

            return [
                'status' => 'error',
                'exception' => get_class($exception),
                'message' => $message,
            ];
        }
    }
}
