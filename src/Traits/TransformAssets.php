<?php

namespace Sushidev\Fairu\Traits;

use Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Log;
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

    protected function getFiles(?array $ids = [], ?bool $fetchMeta = false)
    {
        if (empty($ids)) {
            return null;
        }

        sort($ids);


        $result = [];
        foreach ($ids as $id) {
            $result[] = [
                'id' => $id,
                'url' => $this->buildFileUrl($id)
            ];
        }
        if ($fetchMeta !== true) {
            return $result;
        }


        $fingerprint = md5(json_encode($ids));

        return Cache::flexible('file-' . $fingerprint, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids, $result) {
            $files = null;
            try {
                $files = (new Fairu($this->getConnectionName()))->getFiles($ids);
            } catch (\Exception $e) {
                Log::error($e);
            }
            return $files !== null ? $files : $result;
        });
    }

    protected function getFile(?string $id = null, ?bool $fetchMeta = false)
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
