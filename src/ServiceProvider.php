<?php

namespace Sushidev\Fairu;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;
use Sushidev\Fairu\Services\FairuMetaBag;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\Setup::class,
        Commands\Sync::class,
    ];

    protected $tags = [
        \Sushidev\Fairu\Tags\FairuAssetTags::class,
    ];

    protected $fieldtypes = [
        \Sushidev\Fairu\Fieldtypes\Fairu::class,
    ];

    protected $middlewareGroups = [
        'statamic.web' => [
            \Sushidev\Fairu\Http\Middleware\CoalesceFairuMeta::class,
        ],
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
        'web' => __DIR__ . '/../routes/web.php',
    ];

    public function bootAddon()
    {
        $packageName = str_replace('\\', '-', strtolower(__NAMESPACE__));

        if (config('statamic.fairu.deactivate_old') == true) {
            $vendorViewsPath = base_path("resources/views/vendor/{$packageName}");

            if (!File::exists($vendorViewsPath)) {
                File::makeDirectory($vendorViewsPath, 0755, true);
            }

            View::addNamespace('fairu', $vendorViewsPath);

            Nav::extend(function ($nav) {
                $nav->remove('Content', 'Assets');
                $nav->content('Assets')
                    ->url(cp_route('fairu.browser'))
                    ->icon('assets');
            });
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'fairu');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'fairu');

        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'statamic.fairu');

        $this->publishes([
            __DIR__ . '/../config/fairu.php' => config_path('statamic/fairu.php'),
        ], 'fairu-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'statamic.fairu');

        $this->app->scoped(FairuMetaBag::class);
    }
}
