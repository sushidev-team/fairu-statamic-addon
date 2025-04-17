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

        Collection::hook('fetched-entries', function ($entries, $next) {

            $ids = collect([]);
            $containers = [

            ];
            
            $entries->map(function($entry) use (&$ids, &$container){
                
                $fields = $entry->blueprint()->fields()->all();

                $fairuFields = collect($fields)->filter(function (Field $field) {
                    return $field->type() === 'fairu';
                });

                $fairuFields->mapWithKeys(function (Field $field) use ($entry,&$containers) {
                    
                    $value = $entry->get($field->handle());

                    if (is_array($value)){

                        $value = array_map(function($id) use ($field, &$containers){

                            if(Str::isUuid($id)){
                                return $id;
                            }
                            
                            $container = data_get($field->config(),'container');
                        
                            $disk = null;

                            if ($container != null && data_get($containers, $container) == null){
                                $containerValue = AssetContainer::findByHandle($container);
                                data_set($containers, $container, $containerValue?->disk);
                            }
                            
                            if (data_get($containers, $container) != null){
                                $disk = data_get($containers, $container);
                            }

                            return (new Fairu())->convertToUuid(Storage::disk($disk)->url($id));

                        }, $value);

                    }
                    else if(Str::isUuid($value)){
                        return $value;
                    }
                    else {

                        $container = data_get($field->config(),'container');
                        
                        $disk = null;

                        if ($container != null && data_get($containers, $container) == null){
                            $containerValue = AssetContainer::findByHandle($container);
                            data_set($containers, $container, $containerValue?->disk);
                        }
                        
                        if (data_get($containers, $container) != null){
                            $disk = data_get($containers, $container);
                        }

                        $value = (new Fairu())->convertToUuid(Storage::disk($disk)->url($value));

                    }

                    return [$field->handle() => $value];
                })->flatten()->each(function($id) use (&$ids){

                    if (Str::isUuid($id)){
                        $ids->push($id);
                    }
                    else {
                        $ids->push((new Fairu())->parse($id));
                    }

                });

            });

            $assets = Cache::flexible('fairu-assets-bulk-'.sha1(json_encode($ids->toArray())), [30, 360], function() use ($ids){
                return ((new Fairu())->getFiles($ids->toArray()));
            });

            foreach($assets as $asset){
                Cache::put('fairu-assets-'. data_get($asset, 'id'), $asset, now()->addMinutes(5));
            }

            $entries = $next($entries);
        
            return $entries;
        });

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
