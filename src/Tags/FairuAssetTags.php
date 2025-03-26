<?php

namespace Sushidev\Fairu\Tags;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\SeoPro\Cascade;
use Statamic\SeoPro\GetsSectionDefaults;
use Statamic\SeoPro\RendersMetaHtml;
use Statamic\SeoPro\SiteDefaults;
use Statamic\Tags\Tags;
use Sushidev\Fairu\Services\Fairu;
use Throwable;

class FairuAssetTags extends Tags
{

    protected static $handle = 'fairu';

    protected function getFile(string $id)
    {
        return Cache::flexible('file-' . $id, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () {
            try {
                return data_get((new Fairu($this->params->get('connection', 'default')))->getFile($this->params->get('id')), 'data');
            } catch (Throwable $ex) {
                Log::error($ex->getMessage());
                return null;
            }
        });
    }

    protected function getUrl(string $id, ?string $filename)
    {
        $params = [
            'width' => $this->params->get('width'),
            'height' => $this->params->get('height'),
            'quality' => $this->params->get('quality', 90),
            'format' => $this->params->get('format'),
            'focal' => $this->params->get('focal_point'),
        ];

        $queryString = http_build_query($params);

        return (Str::endsWith(config('fairu.url_proxy'), "/") ? config('fairu.url_proxy') : config('fairu.url_proxy') . "/") . $id . "/" . ($filename ?? 'file') . '?' . $queryString;
    }

    /**
     * The {{ fairu:url }} tag.
     *
     * @return string
     */
    public function url()
    {
        return $this->getUrl($this->params->get('id'), $this->params->get('name', 'file'));
    }

    /**
     * The {{ fairu }} tag.
     *
     * @return string
     */
    public function index()
    {

        $cacheKey = md5(json_encode($this->params->toArray()));

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () {

            $file = $this->getFile($this->params->get('id'));
            $url = $this->getUrl($this->params->get('id'), $this->params->get('name') ?? data_get($file, 'name'));

            $set = data_get($file, 'data');
            data_set($set, 'url', $url,);

            data_set($set, 'fields', array_keys($set));

            return $set;
        });
    }

    /**
     * The {{ fairu:image }} tag.
     *
     * @return string
     */
    public function image()
    {

        $cacheKey = md5(json_encode($this->params->toArray()));

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () {

            $file = $this->getFile($this->params->get('id'));
            $url = $this->getUrl($this->params->get('id'), $this->params->get('name') ?? data_get($file, 'name'));
            if ($url == null) return null;

            $image_params = [
                !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                !empty($this->params->get('alt')) ? "alt='" . $this->params->get('alt') . "'" : data_get($file, 'description'),
            ];

            $image_params = array_filter($image_params);
            $attributes = implode(' ', $image_params);


            return "<img src='$url' $attributes >";
        });
    }
}
