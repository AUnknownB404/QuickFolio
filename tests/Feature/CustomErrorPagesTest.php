<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class CustomErrorPagesTest extends TestCase
{
    public function test_not_found_uses_the_custom_error_page(): void
    {
        $this->get('/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSeeText('Page not found')
            ->assertSeeText('Back to home');
    }

    public function test_server_error_uses_the_custom_page_without_exposing_exception_details(): void
    {
        config(['app.debug' => false]);

        Route::get('/custom-error-page-test', static function () {
            throw new RuntimeException('Sensitive exception details');
        });

        $this->get('/custom-error-page-test')
            ->assertInternalServerError()
            ->assertSeeText('Something went wrong')
            ->assertDontSeeText('Sensitive exception details');
    }
}
