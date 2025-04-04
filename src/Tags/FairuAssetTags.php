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

    public Fairu $fairu;

    public function __construct()
    {
        $this->fairu = (new Fairu());
    }

    protected function getFile(?string $id = null)
    {

        if ($id == null){
            return null;
        }

        $id = $this->fairu->parse($id);
        return Cache::flexible('file-' . $id, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($id) {
            try {
                return data_get((new Fairu($this->params->get('connection', 'default')))->getFile($id), 'data');
            } catch (Throwable $ex) {
                Log::error($ex->getMessage());
                return null;
            }
        });
    }

    protected function getUrl(?string $id = null, ?string $filename = null)
    {

        if ($id == null){
            return null;
        }

        $params = [
            'width' => $this->params->get('width'),
            'height' => $this->params->get('height'),
            'quality' => $this->params->get('quality', 90),
            'format' => $this->params->get('format'),
            'focal' => $this->params->get('focal_point'),
        ];

        $queryString = http_build_query($params);

        $id = $this->fairu->parse($id);

        return (Str::endsWith(config('fairu.url_proxy'), "/") ? config('fairu.url_proxy') : config('fairu.url_proxy') . "/") . $id . "/" . ($filename ?? 'file') . '?' . $queryString;
    }

    /**
     * The {{ fairu:url }} tag.
     *
     * @return string
     */
    public function url()
    {
        $id = $this->fairu->parse($this->params->get('id'));
        return $this->getUrl($id, $this->params->get('name', 'file'));
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

            $id = $this->fairu->parse($this->params->get('id'));
            $file = $this->getFile($id);
            $url = $this->getUrl($id, $this->params->get('name') ?? data_get($file, 'name'));

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

            $id = $this->fairu->parse($this->params->get('id'));
            $file = $this->getFile($id);
            $url = $this->getUrl($id, $this->params->get('name') ?? data_get($file, 'name'));
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

    /**
     * The {{ fairu:sources }} tag.
     * Usage: {{ fairu:sources id="image_id" sources="1200:1600w,768:1200w,480:800w" sizes="(min-width: 1200px) 1600px, (min-width: 768px) 1200px, 800px" [other params] }}
     *
     * @return string
     */
    public function sources()
    {
        $cacheKey = md5(json_encode($this->params->toArray()));

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () {
            
            $id = $this->fairu->parse($this->params->get('id'));
            $file = $this->getFile($id);
            $defaultUrl = $this->getUrl($id, $this->params->get('name') ?? data_get($file, 'name'));

            if ($defaultUrl == null) return null;

            // Parse the sources parameter
            $sourcesParam = $this->params->get('sources');
            $srcsetEntries = [];
            $breakpoints = [];

            if (!empty($sourcesParam)) {
                // Format: "1200:1600w,768:1200w,480:800w"
                $sourcesList = explode(',', $sourcesParam);

                foreach ($sourcesList as $sourceItem) {
                    $parts = explode(':', trim($sourceItem));
                    if (count($parts) === 2) {
                        $breakpoint = trim($parts[0]);
                        $maxWidth = trim($parts[1]);

                        // Extract numeric width from "1200w" format
                        $widthValue = (int)str_replace('w', '', $maxWidth);
                        $breakpoints[] = [
                            'width' => $breakpoint,
                            'imageWidth' => $widthValue
                        ];

                        // Generate URL for this width
                        $baseUrl = $this->getUrl(
                            $id,
                            $this->params->get('name') ?? data_get($file, 'name')
                        );

                        // Add width parameter to URL
                        $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
                        $url = $baseUrl . "{$separator}width={$widthValue}";

                        // Add to srcset entries
                        $srcsetEntries[] = "{$url} {$maxWidth}";
                    }
                }
            }

            // Get sizes attribute if provided, or generate a default one
            $sizesAttr = $this->params->get('sizes');
            if (empty($sizesAttr) && !empty($breakpoints)) {
                // Generate default sizes based on breakpoints if not provided
                $sizesEntries = [];

                // Sort breakpoints from largest to smallest
                usort($breakpoints, function ($a, $b) {
                    return $b['width'] - $a['width'];
                });

                foreach ($breakpoints as $index => $breakpoint) {
                    if ($index === count($breakpoints) - 1) {
                        // Last entry (smallest size, no media query)
                        $sizesEntries[] = "{$breakpoint['imageWidth']}px";
                    } else {
                        // Other entries with media queries
                        $sizesEntries[] = "(min-width: {$breakpoint['width']}px) {$breakpoint['imageWidth']}px";
                    }
                }
                $sizesAttr = implode(", ", $sizesEntries);
            }

            // Build common image attributes
            $image_params = [
                !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                !empty($this->params->get('alt')) ? "alt='" . $this->params->get('alt') . "'" : data_get($file, 'description'),
                !empty($srcsetEntries) ? "srcset='" . implode(", ", $srcsetEntries) . "'" : null,
                !empty($sizesAttr) ? "sizes='" . $sizesAttr . "'" : null,
            ];

            $image_params = array_filter($image_params);
            $attributes = implode(' ', $image_params);

            // Build the img tag
            return "<img src='$defaultUrl' $attributes>";
        });
    }
}
