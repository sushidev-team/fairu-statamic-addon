<?php

namespace Sushidev\Fairu;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;
use Sushidev\Fairu\Http\Controllers\CacheUtilityController;
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
        \Sushidev\Fairu\Fieldtypes\GallerySelector::class,
        \Sushidev\Fairu\Fieldtypes\ChannelSelector::class,
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
                    ->icon('assets')
                    ->can('view fairu assets');
            });
        }

        $viewsPath = __DIR__ . '/../resources/views';
        if (File::isDirectory($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'fairu');
        }
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'fairu');

        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'statamic.fairu');

        $this->publishes([
            __DIR__ . '/../config/fairu.php' => config_path('statamic/fairu.php'),
        ], 'fairu-config');

        /*
         * Utilities → Fairu. Registered through the repository's extension
         * point rather than at boot: utilities are booted per CP request, and a
         * registration made here directly would be thrown away before the
         * router asks for it.
         */
        Utility::extend(function ($utilities) {
            $utilities->register('fairu')
                ->title(__('fairu::utility.title'))
                ->navTitle(__('fairu::utility.nav_title'))
                ->icon(file_get_contents(__DIR__ . '/../resources/svg/fairu-favicon.svg'))
                ->description(__('fairu::utility.description'))
                ->inertia('fairu/CacheUtility', fn ($request) => CacheUtilityController::data($request))
                ->routes(function ($router) {
                    $router->post('clear-meta', [CacheUtilityController::class, 'clearMeta'])->name('clear-meta');
                    $router->post('test-connection', [CacheUtilityController::class, 'testConnection'])->name('test-connection');
                    $router->post('purge-delivery', [CacheUtilityController::class, 'purgeDelivery'])->name('purge-delivery');
                });
        });

        Permission::group('fairu', 'Fairu Assets', function () {
            Permission::register('view fairu assets')->label('View Fairu assets');
            Permission::register('upload fairu assets')->label('Upload Fairu assets');
            Permission::register('edit fairu assets')->label('Edit Fairu asset metadata');
            Permission::register('rename fairu assets')->label('Rename Fairu assets');
            Permission::register('move fairu assets')->label('Move Fairu assets and manage folders');
            Permission::register('delete fairu assets')->label('Delete Fairu assets');
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'statamic.fairu');

        $this->app->scoped(FairuMetaBag::class);
    }
}
