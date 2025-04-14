<?php

namespace Sushidev\Fairu\Tags;

use Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Statamic\Tags\Tags;
use Sushidev\Fairu\Services\Fairu;

class FairuAssetTags extends Tags
{

    protected static $handle = 'fairu';

    public Fairu $fairu;

    public function __construct()
    {
        $this->fairu = (new Fairu());
    }

    protected function resolveIds(array|string|null $ids = null)
    {

        if ($ids == null) {
            return null;
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        } else {
            sort($ids);
        }

        $ids = array_map(function ($id) {

            if (Str::isUuid($id)) {
                return $id;
            }

            return (new Fairu())->resolveOldAssetPath($id);
        }, $ids);

        return $ids;
    }

    protected function buildFileUrl(string $id, ?string $filename = null)
    {
        $baseUrl = Str::endsWith(config('fairu.url_proxy'), "/")
            ? config('fairu.url_proxy')
            : config('fairu.url_proxy') . "/";

        return $baseUrl . $id . "/" . ($filename ?? 'file');
    }

    protected function getFiles(?array $ids = [], ?bool $skipMeta = false)
    {
        if (empty($ids)) {
            return null;
        }

        sort($ids);


        if ($skipMeta === true) {
            $result = [];
            foreach ($ids as $id) {
                $result[] = [
                    'id' => $id,
                    'url' => $this->buildFileUrl($id)
                ];
            }
            return $result;
        }


        $fingerprint = md5(json_encode($ids));

        return Cache::flexible('file-' . $fingerprint, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($ids) {
            $files = (new Fairu($this->params->get('connection', 'default')))->getFiles($ids);
            return $files;
        });
    }

    protected function getFile(?string $id = null, ?bool $skipMeta = false)
    {
        if (!$id) {
            return;
        }
        return Arr::get($this->getFiles([$id], $skipMeta), 0);
    }

    protected function getUrl(
        ?string $id = null,
        ?string $filename = null,
        ?int $width = null,
        ?int $height = null,
        ?string $focalPoint = "50-50-1"
    ): string | null {

        if ($id == null) {
            return null;
        }

        $params = [
            'width' => $width ?? $this->params->get('width'),
            'height' => $height ?? $this->params->get('height'),
            'quality' => $this->params->get('quality', 90),
            'format' => $this->params->get('format'),
            'focal' => $focalPoint,
        ];

        $queryString = http_build_query($params);

        $id = $this->fairu->parse($id);

        return $this->buildFileUrl($id, $filename) . '?' . $queryString;
    }

    protected function getSources($asset, ?string $sourcesParam = null, ?string $name = null, ?string $ratio = null)
    {
        $srcset_entries = [];
        $breakpoints = [];

        $ratioMultiplier = null;
        if (!empty($ratio) && strpos($ratio, '/') !== false) {
            list($numerator, $denominator) = explode('/', $ratio);
            if (is_numeric($numerator) && is_numeric($denominator) && $denominator != 0) {
                $ratioMultiplier = (float)$numerator / (float)$denominator;
            }
        }

        if (!empty($sourcesParam)) {
            // Format with semicolons as source separators:
            // "100,100,200w;480,480,800w;768,768,1200w" (3 params: width, *height*, maxWidth)
            // "100,200w;480,800w;768,1200w" (2 params: width, maxWidth)
            $sourcesList = explode(';', $sourcesParam);

            foreach ($sourcesList as $sourceItem) {
                $parts = explode(',', trim($sourceItem));

                // Check if we have enough parts to process
                if (count($parts) >= 2) {
                    $width = (int) trim($parts[0]);
                    $height = null;
                    $maxWidth = null;

                    if (count($parts) === 3) {
                        // Format with height: width,height,maxWidthw
                        $height = (int) trim($parts[1]);
                        $maxWidth = trim($parts[2]);
                    } else {
                        // Format without height: width,maxWidthw
                        $maxWidth = trim($parts[1]);
                        // Calculate height from ratio if ratio is provided
                        if ($ratioMultiplier !== null) {
                            $height = (int)($width / $ratioMultiplier);
                        }
                    }

                    // Add to breakpoints
                    $breakpointData = [
                        'width' => $width,
                        'maxWidth' => $maxWidth
                    ];

                    if ($height !== null) {
                        $breakpointData['height'] = $height;
                    }

                    $breakpoints[] = $breakpointData;

                    // Generate URL for this width and height
                    $url = $this->getUrl(
                        id: data_get($asset, 'id'),
                        filename: $name ?? data_get($asset, 'name'),
                        width: $width,
                        height: $height,
                        focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point')
                    );

                    // Add to srcset entries
                    $srcset_entries[] = "{$url} {$maxWidth}";
                }
            }
        }

        return $srcset_entries;
    }

    function formatFocalPoint($focalPoint)
    {
        if (!$focalPoint) {
            return '50% 50%';
        }

        $parts = explode('-', $focalPoint);

        if (count($parts) >= 2) {
            return $parts[0] . '% ' . $parts[1] . '%';
        }

        return '50% 50%';
    }


    /**
     * The {{ fairu:url }} tag.
     *
     * @return array
     */
    public function url()
    {
        $ids = $this->resolveIds();

        return array_map(function ($id) {
            $id = $this->fairu->parse($id);
            return $this->getUrl(
                id: $id,
                filename: $this->params->get('name') ?? 'file',
                focalPoint: $this->params->get('focal_point')
            );
        }, $ids);
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

        $files = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($ids) {
            return collect($this->getFiles($ids, $this->params->get('skipMeta')))->map(function ($asset) {

                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point')
                );
                $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));
                if (!empty($srcset_entries)) {
                    data_set($asset, 'srcset', implode(", ", $srcset_entries));
                }
                data_set($asset, 'url', $url);
                data_set($asset, 'focus_css', $this->formatFocalPoint($this->params->get('focal_point') ?? data_get($asset, 'focal_point')));
                data_set($asset, 'fields', array_keys($asset));

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

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($id) {
            $asset = $this->getFile($id, $this->params->get('skipMeta'));
            $url = $this->getUrl(
                id: data_get($asset, 'id'),
                filename: $this->params->get('name') ?? data_get($asset, 'name'),
                focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point')
            );
            data_set($asset, 'url', $url);
            data_set($asset, 'fields', array_keys($asset));

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

        $imgStrings = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('fairu.caching_meta'), function () use ($ids) {
            return collect($this->getFiles($ids))->map(function ($asset) {
                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point')
                );
                data_set($asset, 'url', $url);
                data_set($asset, 'fields', array_keys($asset));

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
