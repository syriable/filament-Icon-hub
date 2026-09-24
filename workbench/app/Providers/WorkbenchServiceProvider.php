<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('icon-hub.local', [
            'brand' => ['path' => dirname(__DIR__, 2).'/resources/icons', 'label' => 'Brand'],
        ]);
        config()->set('icon-hub.library.enabled', true);

        $this->app->register(AdminPanelProvider::class);
    }
}
