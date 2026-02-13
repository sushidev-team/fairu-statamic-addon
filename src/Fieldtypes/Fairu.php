<?php

namespace Sushidev\Fairu\Fieldtypes;

use Illuminate\Support\Facades\Cache;
use Statamic\Fields\Fieldtype;
use Statamic\Exceptions\AssetContainerNotFoundException;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Fieldtypes\Assets\UndefinedContainerException;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Illuminate\Support\Str;
use Statamic\Assets\Asset as AssetsAsset;
use Sushidev\Fairu\Traits\TransformAssets;

class Fairu extends Fieldtype
{
    use TransformAssets;

    protected $icon = 'addons';

    public $categories = ['media'];


    public static $title = 'Fairu Assets';

    public static function handle(): string
    {
        return 'fairu';
    }

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
        if ($data == null) {
            return $data;
        }

        if (!is_array($data)) {
            $data = [$data];
        }
        if (is_array($data)) {
            return collect($data)->map(function ($item) {
                // If item is already an array (parsed object), return as-is
                if (is_array($item)) {
                    return $item;
                }
                if (Str::isUuid($item)) {
                    return $item;
                }
                return Cache::flexible('asset-container-item-' . sha1($item), [120, 240], function () use ($item) {
                    return (new ServicesFairu)->parse($item, data_get($this->config(), 'container'));
                });
            })->toArray();
        }

        if (Str::isUuid($data)) {
            return $data;
        }

        return Cache::flexible('asset-container-item-' . sha1($data), [120, 240], function () use ($data) {
            return (new ServicesFairu)->parse($data);
        });
    }




    public function preload()
    {
        return ['proxy' => config('fairu.url_proxy'), 'file' => config('fairu.url') . '/files'];
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
        if (!$data) {
            return null;
        }

        return $this->config('max_files') === 1 ? $data[0] : $data;
    }

    public function rules(): array
    {
        $rules = ['array'];

        if ($max = $this->config('max_files')) {
            $rules[] = 'max:' . $max;
        }

        if ($min = $this->config('min_files')) {
            $rules[] = 'min:' . $min;
        }

        return $rules;
    }

    protected function container() {}

    protected function configFieldItems(): array
    {
        return [
            'section_fairu' => [
                'display' => 'Fairu',
                'type' => 'section',
            ],
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
            'display_type' => [
                'display' => 'Display type',
                'type' => 'select',
                'default' => 'list',
                'options' => [
                    'list' => __('fairu::browser.display_types.list'),
                    'tiles' => __('fairu::browser.display_types.tiles'),
                ],
                'instructions' => 'The folder to begin browsing in.',
            ],
            'allow_uploads' => [
                'display' => 'Allow uploads',
                'instructions' => '',
                'type' => 'toggle',
                'default' => true
            ],
            'folder' => [
                'display' => 'Folder',
                'instructions' => 'The folder to begin browsing in.',
                'type' => 'folder_selector',
            ],

        ];
    }

    public function augment($value)
    {
        return $value;
    }

    public function shallowAugment($value)
    {
        $cacheKey = md5(json_encode($value));
        $ids = $this->resolveIds($value);

        $files = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($ids) {
            return collect($this->getFiles($ids, true))->map(function ($asset) {

                $url = $this->buildFileUrl(
                    id: data_get($asset, 'id'),
                    filename: data_get($asset, 'name'),
                );

                data_set($asset, 'url', $url);
                data_set($asset, 'focus_css', $this->formatFocalPoint(data_get($asset, 'focal_point')));

                return $asset;
            });
        })?->toArray();

        if (!is_array($files)) return $files;

        return $this->config('max_files') === 1 ? data_get($files, 0) : $files;
    }
}
