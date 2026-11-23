<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Infrastructure\Cms\AdminPathMode;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminPathModeTest extends TestCase
{
    public function test_livewire_posts_to_its_own_prefix_by_default(): void
    {
        self::assertStringStartsWith('/livewire-', Livewire::getUpdateUri());
    }

    public function test_path_mode_moves_the_update_endpoint_under_admin(): void
    {
        AdminPathMode::apply();

        self::assertSame('/admin/livewire/update', Livewire::getUpdateUri());

        // Livewire names the route after adding it, so the lookups are refreshed first (the application does it when it routes a request).
        Route::getRoutes()->refreshNameLookups();
        $route = Route::getRoutes()->getByName('livewire.update');
        self::assertNotNull($route);
        self::assertSame('admin/livewire/update', $route->uri());
        self::assertContains('web', $route->middleware(), 'the update endpoint keeps the session and the forgery check');
    }

    public function test_the_path_mode_is_off_unless_the_environment_asks_for_it(): void
    {
        self::assertFalse(config()->boolean('cms.admin_path_mode'));
    }
}
