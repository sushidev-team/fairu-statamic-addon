<?php

namespace Sushidev\Fairu;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\Setup::class,
    ];

    protected $tags = [
        \Sushidev\Fairu\Tags\FairuAssetTags::class,
    ];

    protected $fieldtypes = [
        \Sushidev\Fairu\Fieldtypes\Fairu::class,
    ];

    protected $translations = [
        'en' => [
            'fieldtype' => __DIR__.'/../resources/lang/en/fieldtype.php',
        ],
        'de' => [
            'fieldtype' => __DIR__.'/../resources/lang/de/fieldtype.php',
        ],
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/web.php',
    ];

    protected $config = false;

    public function bootAddon()
    {
        $packageName = str_replace('\\', '-', strtolower(__NAMESPACE__));

        if (config('statamic.fairu.deactivate_old') == true) {
            $vendorViewsPath = base_path("resources/views/vendor/{$packageName}");

            // Automatically create the vendor views directory if it doesn't exist
            if (! File::exists($vendorViewsPath)) {
                File::makeDirectory($vendorViewsPath, 0755, true);
            }

            View::addNamespace('fairu-statamic', $vendorViewsPath);
        }
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fairu');
        $this->bootAddonConfig();

        parent::bootAddon();
    }

    protected function bootAddonConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fairu.php', 'statamic.fairu');

        $this->publishes([
            __DIR__.'/../config/fairu.php' => config_path('statamic/fairu.php'),
        ], 'fairu-config');

        return $this;
    }

    public function register()
    {
        if (config('statamic.fairu.deactivate_old') == true) {
            $this->app->bind(\Statamic\Http\Controllers\CP\Utilities\CacheController::class, \Sushidev\Fairu\Http\Controllers\CacheController::class);
        }

        $this->mergeConfigFrom(__DIR__.'/../config/fairu.php', 'fairu');
    }
}
