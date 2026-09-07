<?php

namespace Sushidev\Fairu\Services;

use Statamic\Facades\Antlers;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Collection;
use Illuminate\View\ComponentAttributeBag;
use Sushidev\Fairu\Traits\TransformAssets;

/** Shared renderer for Antlers tags, Blade tags, components and deferred output. */
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

        $output = match ($type) {
            'image' => $this->renderImage($params, $asset),
            'url' => $this->renderUrl($params, $asset),
            default => '',
        };

        return $type === 'url' && ($params['_escape'] ?? false) ? e($output) : $output;
    }

    /**
     * Render a deferred {{ fairu }} list tag by building each asset the same way
     * FairuAssetTags::index() does and parsing the tag body once per asset.
     *
     * @param  array<int, array<string, mixed>>  $assets  resolved meta for the ids in order
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context  outer Antlers context captured at queue time
     */
    public function renderList(array $assets, array $params, string $body, array $context, string $connection = 'default', string $language = 'antlers'): string
    {
        $this->renderParams = $params;
        $this->renderConnection = $connection;

        if ($body === '') {
            return '';
        }

        if ($language === 'blade') {
            return Blade::render(
                '@foreach ($assets as $asset){!! $renderAsset($asset, $loop) !!}@endforeach',
                [
                    'assets' => $assets,
                    'renderAsset' => function ($asset, $loop) use ($params, $body, $context) {
                        $asset = $this->augmentAsset((array) $asset, $params);
                        $data = array_merge($context, isset($params['scope']) ? [$params['scope'] => $asset] : $asset);
                        $loop->parent = $context['loop'] ?? $loop->parent;
                        $loop->depth = ($loop->parent->depth ?? 0) + 1;
                        $data['loop'] = $loop;

                        return Blade::render($body, $data);
                    },
                ],
            );
        }

        $out = '';

        foreach ($assets as $asset) {
            $asset = $this->augmentAsset((array) $asset, $params);
            $data = array_merge($context, $asset);

            // This is trusted template source. Antlers partials require trusted parsing.
            $out .= (string) Antlers::parse($body, $data, true);
        }

        return $out;
    }

    public function assets(array $assets, array $params, string $connection = 'default'): Collection
    {
        $this->renderParams = $params;
        $this->renderConnection = $connection;

        return collect($assets)->map(fn ($asset) => $this->augmentAsset($asset, $params));
    }

    /**
     * Add the same URL, responsive sources and focal point data in both languages.
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

        if ($this->wantsDownload()) {
            return (string) $this->downloadUrl($id, $filename ?? 'file');
        }

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

        $attributes = new ComponentAttributeBag(array_map(
            fn ($value) => is_string($value) ? e($value, false) : $value,
            $params['_attributes'] ?? [],
        ));
        $generated = [
            'src' => $url,
            'alt' => strip_tags((string) ($params['alt'] ?? data_get($asset, 'alt') ?? data_get($asset, 'description') ?? '')),
        ];
        foreach (['width', 'height', 'class', 'sizes', 'loading', 'decoding', 'fetchpriority'] as $key) {
            if (isset($params[$key])) {
                $generated[$key] = $params[$key];
            }
        }
        if ($srcsetEntries !== []) {
            $generated['srcset'] = implode(', ', $srcsetEntries);
        }

        return '<img '.$attributes->merge($generated).'>';
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
