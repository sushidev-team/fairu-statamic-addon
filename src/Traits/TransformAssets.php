<?php

namespace Sushidev\Fairu\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Sushidev\Fairu\Services\Fairu;

trait TransformAssets
{
    public Fairu $fairu;

    public function __construct()
    {
        $this->fairu = (new Fairu());
    }

    protected function getConnectionName()
    {
        // If used in a Tag context
        if (property_exists($this, 'params') && method_exists($this->params, 'get')) {
            return $this->params->get('connection', 'default');
        }

        // If used in a Fieldtype context
        if (method_exists($this, 'config')) {
            return $this->config('connection', 'default');
        }

        return 'default';
    }

    /**
     * Read the fetchMeta flag accepting either camelCase or snake/kebab variants.
     * Returns the raw value so callers can distinguish between "true", "full", and false.
     */
    protected function fetchMetaParam(): mixed
    {
        if (! property_exists($this, 'params') || ! is_object($this->params)) {
            return false;
        }

        foreach (['fetchMeta', 'fetch_meta', 'fetch-meta'] as $key) {
            $value = $this->params->get($key);
            if ($value !== null) {
                return $value;
            }
        }

        return false;
    }

    /**
     * Safely get a parameter value from the appropriate context.
     *
     * @param string $key The parameter key
     * @param mixed $default The default value if parameter doesn't exist
     * @param mixed $override An optional override value that takes precedence
     * @return mixed The parameter value
     */
    protected function getParam(string $key, $override = null, $default = null)
    {
        // If an override is provided, use it
        if ($override !== null) {
            return $override;
        }

        // Check if we're in a Tag context
        if (property_exists($this, 'params') && is_object($this->params) && method_exists($this->params, 'get')) {
            return $this->params->get($key, $default);
        }

        // Check if we're in a controller context with request
        if (method_exists($this, 'request')) {
            return $this->request->input($key, $default);
        }

        // Fallback to default
        return $default;
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

    protected function buildFileUrl(?string $id, ?string $filename = null)
    {
        if ($id == null) {
            return null;
        }

        $baseUrl = Str::endsWith(config('statamic.fairu.url_proxy'), "/")
            ? config('statamic.fairu.url_proxy')
            : config('statamic.fairu.url_proxy') . "/";

        return $baseUrl . $id . "/" . ($filename ?? 'file');
    }

    protected function getUrl(
        ?string $id = null,
        ?string $filename = null,
        ?int $width = null,
        ?int $height = null,
        ?string $focalPoint = "50-50-1",
        ?string $fit = null,
        ?bool $appendQuery = false
    ): string | null {

        $queryString = '';

        if ($id == null) {
            return null;
        }

        if ($appendQuery) {
            $params = [
                'width' => $this->getParam('width', $width),
                'height' => $this->getParam('height', $height),
                'quality' => $this->getParam('quality', null, 90),
                'timestamp' => $this->getParam('timestamp'),
                'format' => $this->getParam('format'),
                'fit' => $this->getParam('fit'),
                'focal' => $focalPoint,
            ];

            $queryString = http_build_query($params);
        }


        $id = $this->fairu->parse($id);

        $url = $this->buildFileUrl($id, $filename);
        $url .= ($appendQuery) ? '?' .  $queryString : '';
        return $url;
    }

    protected function getSources($asset, ?string $sourcesParam = null, ?string $name = null, ?string $ratio = null)
    {
        $srcset_entries = [];

        $ratioMultiplier = null;
        if (!empty($ratio) && strpos($ratio, '/') !== false) {
            list($numerator, $denominator) = explode('/', $ratio);
            if (is_numeric($numerator) && is_numeric($denominator) && $denominator != 0) {
                $ratioMultiplier = (float)$numerator / (float)$denominator;
            }
        }

        if (empty($sourcesParam)) {
            return $srcset_entries;
        }

        $sourcesList = explode(';', $sourcesParam);

        foreach ($sourcesList as $sourceItem) {
            $parts = explode(',', trim($sourceItem));

            if (count($parts) < 2) {
                continue;
            }

            $width = (int) trim($parts[0]);
            $height = null;
            $maxWidth = null;

            if (count($parts) === 3) {
                $height = (int) trim($parts[1]);
                $maxWidth = trim($parts[2]);
            } else {
                $maxWidth = trim($parts[1]);
                if ($ratioMultiplier !== null) {
                    $height = (int)($width / $ratioMultiplier);
                }
            }

            $url = $this->getUrl(
                id: data_get($asset, 'id'),
                filename: $name ?? data_get($asset, 'name'),
                width: $width,
                height: $height,
                fit: $this->getParam('fit') ?? data_get($asset, 'fit'),
                focalPoint: $this->getParam('focal_point') ?? data_get($asset, 'focal_point'),
                appendQuery: true
            );

            $srcset_entries[] = "{$url} {$maxWidth}";
        }

        return $srcset_entries;
    }

    protected function getFiles(?array $ids = [], mixed $fetchMeta = false)
    {
        if (empty($ids)) {
            return null;
        }

        sort($ids);

        $mode = $this->resolveFetchMetaMode($fetchMeta);

        $result = [];
        foreach ($ids as $id) {
            $result[] = [
                'id' => $id,
                'url' => $this->buildFileUrl($id)
            ];
        }

        if ($mode === null) {
            return $result;
        }

        $fingerprint = md5($mode . '-' . json_encode($ids));

        return Cache::flexible('file-' . $mode . '-' . $fingerprint, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids, $mode, $result) {
            $files = null;
            try {
                $fairu = new Fairu($this->getConnectionName());
                $files = $mode === 'full'
                    ? $fairu->getFiles($ids)
                    : $fairu->getFilesMeta($ids);
            } catch (\Exception $e) {
                Log::error($e);
            }
            return $files !== null ? $files : $result;
        });
    }

    /**
     * Resolve the fetchMeta parameter into a fetch mode.
     *
     * - false / null / "" / "0"  → null   (no meta fetch, skip API call)
     * - "full"                    → "full" (hit /api/files/list, heavy payload with licenses/blocks)
     * - any other truthy value    → "meta" (hit /api/files/meta, lean payload)
     */
    protected function resolveFetchMetaMode(mixed $fetchMeta): ?string
    {
        if (is_string($fetchMeta) && strtolower($fetchMeta) === 'full') {
            return 'full';
        }

        return filter_var($fetchMeta, FILTER_VALIDATE_BOOLEAN) ? 'meta' : null;
    }

    protected function getFile(?string $id = null, mixed $fetchMeta = false)
    {
        if (!$id) {
            return;
        }
        return Arr::get($this->getFiles([$id], $fetchMeta), 0);
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
}
