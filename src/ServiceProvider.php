<?php

namespace SushidevTeam\Fairu;

use Illuminate\Support\Facades\View;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Facades\Utility;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\Setup::class,
    ];

    protected $tags = [
        \SushidevTeam\Fairu\Tags\FairuAssetTags::class,
    ];

    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
        'web' => __DIR__ . '/../routes/web.php',
    ];

    public function bootAddon()
    {
        $packageName = str_replace('\\', '-', strtolower(__NAMESPACE__)); // z. B. "fairu-statamic"

        if (config('fairu.deactivate_old') == true){
            View::addNamespace('fairu-statamic', base_path("resources/views/vendor/{$packageName}"));
        }
    }

    public function register()
    {
        if (config('fairu.deactivate_old') == true){
            $this->app->bind(\Statamic\Http\Controllers\CP\Utilities\CacheController::class, \SushidevTeam\Fairu\Http\Controllers\CacheController::class);
        }
    }
}
