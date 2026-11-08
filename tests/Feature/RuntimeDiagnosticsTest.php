<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_diagnostics_require_a_configured_secret_token(): void
    {
        config(['services.diagnostics.token' => 'test-diagnostics-token']);

        $this->postJson('/_diagnostics')
            ->assertNotFound();
    }

    public function test_runtime_diagnostics_run_without_web_session_middleware(): void
    {
        config(['services.diagnostics.token' => 'test-diagnostics-token']);

        $route = Route::getRoutes()->getByName('runtime.diagnostics');
        $this->assertNotContains('web', $route->gatherMiddleware());

        $this->postJson('/_diagnostics', [], [
            'X-Diagnostics-Token' => 'test-diagnostics-token',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.session.status', 'ok')
            ->assertJsonPath('checks.compiled_views.status', 'ok');
    }

    public function test_runtime_diagnostics_report_a_failed_probe_without_a_stack_trace(): void
    {
        config(['services.diagnostics.token' => 'test-diagnostics-token']);
        Schema::drop('users');

        $this->postJson('/_diagnostics', [], [
            'X-Diagnostics-Token' => 'test-diagnostics-token',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('checks.tables.status', 'error')
            ->assertJsonMissingPath('checks.tables.trace');
    }
}
