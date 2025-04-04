<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;
use Statamic\Exceptions\AssetContainerNotFoundException;
use Statamic\Facades\AssetContainer;
use Statamic\Fieldtypes\Assets\UndefinedContainerException;
use Statamic\Statamic;

class Fairu extends Fieldtype
{
    public function icon()
    {
        return file_get_contents(__DIR__ . '/../../resources/svg/fairu-favicon.svg');
    }

    protected $keywords = ['file', 'files', 'image', 'images', 'video', 'videos', 'audio', 'upload', 'fairu'];

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
        return $data;
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
        return [
            'max_files' => [
                'display' => 'Max Files',
                'instructions' => 'Set a maximum number of selectable assets.',
                'type' => 'integer',
            ],
            'min_files' => [
                'display' => 'Min Files',
                'instructions' => '',
                'type' => 'integer',
            ],
            'allow_uploads' => [
                'display' => 'Allow uploads',
                'instructions' => '',
                'type' => 'toggle',
                'default' => true
            ],
        ];
    }
}
