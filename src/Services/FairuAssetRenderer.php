<?php

namespace Sushidev\Fairu\Services;

use Statamic\Facades\Antlers;
use Sushidev\Fairu\Traits\TransformAssets;

/**
 * Renders the final HTML/URL for a queued fairu tag given its params and the
 * asset meta that was fetched in bulk by CoalesceFairuMeta. Mirrors the
 * behaviour of FairuAssetTags::image() and FairuAssetTags::url() so that a
 * tag rendered via placeholder produces byte-identical output.
 */
class FairuAssetRenderer
{
    use TransformAssets;

    /** @var array<string, mixed> */
    protected array $renderParams = [];

    protected string $renderConnection = 'default';

    public function render(string $type, array $params, ?array $asset, string $connection = 'default'): string
    {
        $this->renderParams = $params;
        $this->renderConnection = $connection;

        return match ($type) {
            'image' => $this->renderImage($params, $asset),
            'url' => $this->renderUrl($params, $asset),
            default => '',
        };
    }

    /**
     * Render a deferred {{ fairu }} list tag by building each asset the same way
     * FairuAssetTags::index() does and parsing the tag body once per asset.
     *
     * @param  array<int, array<string, mixed>>  $assets  resolved meta for the ids in order
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context  outer Antlers context captured at queue time
     */
    public function renderList(array $assets, array $params, string $body, array $context, string $connection = 'default'): string
    {
        $this->renderParams = $params;
        $this->renderConnection = $connection;

        if ($body === '') {
            return '';
        }

        $out = '';

        foreach ($assets as $asset) {
            $asset = $this->augmentAsset((array) $asset, $params);
            $data = array_merge($context, $asset);

            $out .= (string) Antlers::parse($body, $data);
        }

        return $out;
    }

    /**
     * Mirror the url/srcset/focus_css augmentation that FairuAssetTags::index()
     * applies before returning the collection to Antlers.
     */
    protected function augmentAsset(array $asset, array $params): array
    {
        $url = $this->getUrl(
            id: data_get($asset, 'id'),
            filename: $params['name'] ?? data_get($asset, 'name'),
            focalPoint: $params['focal_point'] ?? data_get($asset, 'focal_point'),
            fit: $params['fit'] ?? data_get($asset, 'fit'),
            appendQuery: data_get($asset, 'is_image')
                || ! empty($params['width'])
                || ! empty($params['height'])
                || ! empty($params['sources'])
                || ! empty($params['timestamp']),
        );

        $srcsetEntries = $this->getSources(
            $asset,
            $params['sources'] ?? null,
            $params['name'] ?? null,
            $params['ratio'] ?? null,
        );

        $asset['url'] = $url;
        $asset['focus_css'] = $this->formatFocalPoint($params['focal_point'] ?? data_get($asset, 'focal_point'));

        if (! empty($srcsetEntries)) {
            $asset['srcset'] = implode(', ', $srcsetEntries);
        }

        return $asset;
    }

    protected function renderUrl(array $params, ?array $asset): string
    {
        $id = data_get($asset, 'id') ?? ($params['id'] ?? null);
        $filename = $params['name'] ?? data_get($asset, 'name');

        return (string) $this->getUrl(
            id: $id,
            filename: $filename ?? 'file',
            appendQuery: true,
        );
    }

    protected function renderImage(array $params, ?array $asset): string
    {
        $id = data_get($asset, 'id') ?? ($params['id'] ?? null);

        $url = $this->getUrl(
            id: $id,
            filename: $params['name'] ?? data_get($asset, 'name'),
            focalPoint: $params['focal_point'] ?? data_get($asset, 'focal_point'),
            fit: $params['fit'] ?? data_get($asset, 'fit'),
            appendQuery: data_get($asset, 'is_image')
                || ! empty($params['width'])
                || ! empty($params['height'])
                || ! empty($params['sources'])
                || ! empty($params['timestamp'])
        );

        $srcsetEntries = $this->getSources(
            $asset ?? ['id' => $id],
            $params['sources'] ?? null,
            $params['name'] ?? null,
            $params['ratio'] ?? null
        );

        $altText = $params['alt'] ?? data_get($asset, 'description');

        $attrs = array_filter([
            ! empty($params['width']) ? "width='".$params['width']."'" : null,
            ! empty($params['height']) ? "height='".$params['height']."'" : null,
            ! empty($params['class']) ? "class='".$params['class']."'" : null,
            ! empty($params['alt']) ? "alt='".strip_tags((string) $altText)."'" : null,
            ! empty($params['sizes']) ? "sizes='".$params['sizes']."'" : null,
            ! empty($srcsetEntries) ? "srcset='".implode(', ', $srcsetEntries)."'" : null,
        ]);

        return "<img src='{$url}' ".implode(' ', $attrs).'>';
    }

    /**
     * Override trait behaviour so getUrl() / getSources() read from the
     * render-time params array instead of a Tag's $this->params object.
     */
    protected function getParam(string $key, $override = null, $default = null)
    {
        if ($override !== null) {
            return $override;
        }

        return $this->renderParams[$key] ?? $default;
    }

    protected function getConnectionName()
    {
        return $this->renderConnection;
    }
}
