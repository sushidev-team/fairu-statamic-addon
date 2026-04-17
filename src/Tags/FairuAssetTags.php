<?php

namespace Sushidev\Fairu\Tags;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Statamic\Tags\Tags;
use Sushidev\Fairu\Services\Fairu;
use Sushidev\Fairu\Services\FairuMetaBag;
use Sushidev\Fairu\Traits\TransformAssets;

class FairuAssetTags extends Tags
{
    use TransformAssets;

    protected static $handle = 'fairu';

    /**
     * The {{ fairu:url }} tag.
     *
     * @return array
     */
    public function url()
    {
        $id = Arr::get($this->resolveIds($this->params->get('id')), 0);

        $id = $this->fairu->parse($id);

        $filename = $this->params->get('name');

        if (! $filename && $id !== null && $this->params->bool('fetchMeta')) {
            $bag = app(FairuMetaBag::class);
            if ($bag->isActive()) {
                return $bag->queue('url', $id, $this->params->toArray(), $this->getConnectionName());
            }

            $asset = $this->getFile($id, true);
            $filename = data_get($asset, 'name');
        }

        return $this->getUrl(
            id: $id,
            filename: $filename ?? 'file',
            appendQuery: true
        );
    }

    /**
     * The {{ fairu }} tag.
     *
     * @return array
     */
    public function index()
    {
        $cacheKey = md5(json_encode($this->params->toArray()));

        $ids = $this->params->get('id') ?? $this->params->get('ids');
        $ids = $this->resolveIds($ids);


        $files = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids) {
            return collect($this->getFiles($ids, $this->params->bool('fetchMeta')))->map(function ($asset) {
                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                    fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                    appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp'),
                );
                $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));
                if (!empty($srcset_entries)) {
                    data_set($asset, 'srcset', implode(", ", $srcset_entries));
                }
                data_set($asset, 'url', $url);
                data_set($asset, 'focus_css', $this->formatFocalPoint($this->params->get('focal_point') ?? data_get($asset, 'focal_point')));

                return $asset;
            });
        });

        return $files;
    }

    /**
     * The {{ fairu:image }} tag.
     *
     * @return string
     */
    public function image()
    {

        $cacheKey = 'image-' . md5(json_encode($this->params->toArray()));

        $id = Arr::get($this->resolveIds($this->params->get('id')), 0);
        if (!$id) {
            return;
        }

        $bag = app(FairuMetaBag::class);
        if ($bag->isActive()) {
            return $bag->queue('image', $id, $this->params->toArray(), $this->getConnectionName());
        }

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($id) {
            $asset = $this->getFile($id, $this->params->bool('fetchMeta'));
            $url = $this->getUrl(
                id: data_get($asset, 'id'),
                filename: $this->params->get('name') ?? data_get($asset, 'name'),
                focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp')
            );
            data_set($asset, 'url', $url);

            $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));

            $altText = $this->params->get('alt') ?? data_get($asset, 'description');

            $image_params = [
                !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                !empty($this->params->get('alt')) ? "alt='" . strip_tags($altText) . "'" : null,
                !empty($this->params->get('sizes')) ? "sizes='" . $this->params->get('sizes') . "'" : null,
                !empty($srcset_entries) ? "srcset='" . implode(", ", $srcset_entries) . "'" : null,
            ];

            $image_params = array_filter($image_params);
            $attributes = implode(' ', $image_params);

            return "<img src='$url' $attributes>";
        });
    }

    /**
     * The {{ fairu:images }} tag.
     *
     * @return array
     */
    public function images()
    {

        $cacheKey = 'images-' . md5(json_encode($this->params->toArray()));

        $ids = $this->resolveIds($this->params->get('ids'));
        if (empty($ids)) {
            return;
        }

        $imgStrings = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids) {
            return collect($this->getFiles($ids, $this->params->bool('fetchMeta')))->map(function ($asset) {
                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                    fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                    appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp')
                );
                data_set($asset, 'url', $url);

                $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));

                $altText = $this->params->get('alt') ?? data_get($asset, 'description');

                $image_params = [
                    !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                    !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                    !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                    !empty($altText) ? "alt='" . strip_tags($altText) . "'" : null,
                    !empty($this->params->get('sizes')) ? "sizes='" . $this->params->get('sizes') . "'" : null,
                    !empty($srcset_entries) ? "srcset='" . implode(", ", $srcset_entries) . "'" : null,
                ];

                $image_params = array_filter($image_params);
                $attributes = implode(' ', $image_params);

                return "<img src='$url' $attributes>";
            });
        });

        return $imgStrings?->implode('');
    }
}
