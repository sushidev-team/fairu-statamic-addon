<?php

namespace Sushidev\Fairu;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Tags\Collection\Collection;
use Sushidev\Fairu\Services\Fairu;

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
            'fieldtype' => __DIR__ . '/../resources/lang/en/fieldtype.php',
        ],
        'de' => [
            'fieldtype' => __DIR__ . '/../resources/lang/de/fieldtype.php',
        ],
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__ . '/../resources/dist/hot',
    ];

    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
        'web' => __DIR__ . '/../routes/web.php',
    ];

    protected $config = false;

    public function bootAddon()
    {
        $packageName = str_replace('\\', '-', strtolower(__NAMESPACE__));

        if (config('fairu.deactivate_old') == true) {
            View::addNamespace('fairu-statamic', base_path("resources/views/vendor/{$packageName}"));
        }
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'fairu');
        $this->bootAddonConfig();
    }

    protected function bootAddonConfig()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'statamic.fairu');

        $this->publishes([
            __DIR__ . '/../config/fairu.php' => config_path('statamic/fairu.php'),
        ], 'fairu-config');

        return $this;
    }


    public function register()
    {
        if (config('fairu.deactivate_old') == true) {
            $this->app->bind(\Statamic\Http\Controllers\CP\Utilities\CacheController::class, \Sushidev\Fairu\Http\Controllers\CacheController::class);
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/fairu.php', 'fairu');
    }
}
