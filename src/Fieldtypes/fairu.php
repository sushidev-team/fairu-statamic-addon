<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;
use Statamic\Exceptions\AssetContainerNotFoundException;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Fieldtypes\Assets\UndefinedContainerException;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Illuminate\Support\Str;
use Statamic\Assets\Asset as AssetsAsset;

class Fairu extends Fieldtype
{
    public function icon()
    {
        return file_get_contents(__DIR__ . '/../../resources/svg/fairu-favicon.svg');
    }

    /**
     * The blank/default value.
     *
     * @return array
     */
    public function defaultValue()
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     *
     * @param  mixed  $data
     * @return array|mixed
     */
    public function preProcess($data)
    {
        if ($data == null){
            return $data;
        }

        if (is_array($data)){

            return collect($data)->map(function($item){

                if (Str::isUuid($item)){
                    return $item;
                }

                return (new ServicesFairu)->parse($item);
            })->toArray();

        }

        if (Str::isUuid($data)){
            return $data;
        }

        return (new ServicesFairu)->parse($data);

    }

    public function preload()
    {
        return ['proxy' => config('fairu.url_proxy')];
    }

    public function getItemData($items)
    {
        return $items;
    }

    /**
     * Process the data before it gets saved.
     *
     * @param  mixed  $data
     * @return array|mixed
     */
    public function process($data)
    {
        return $data;
    }

    protected $icon = 'addons';

    public $categories = ['media'];


    public static $title = 'Fairu Assets';

    public static function handle(): string
    {
        return 'fairu';
    }

    protected function container() {}

    protected function configFieldItems(): array
    {
        return [];
    }
}
