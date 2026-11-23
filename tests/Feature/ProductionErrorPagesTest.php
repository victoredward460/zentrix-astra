<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProductionErrorPagesTest extends TestCase
{
    public function test_not_found_page_is_branded_and_does_not_render_framework_diagnostics(): void
    {
        config(['app.debug' => false]);

        $this->get('/phase-thirteen-missing-page')
            ->assertNotFound()
            ->assertSeeText('This page is unavailable')
            ->assertSeeText('404')
            ->assertSee('<script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>', false)
            ->assertDontSee('Laravel Framework');
    }

    public function test_server_error_page_hides_exception_details_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);
        Route::get('/phase-thirteen-server-error', function () {
            throw new RuntimeException('private diagnostic detail');
        });

        $this->get('/phase-thirteen-server-error')
            ->assertServerError()
            ->assertSeeText('Something went wrong')
            ->assertDontSee('private diagnostic detail');
    }
}
