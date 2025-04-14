<?php

namespace Sushidev\Fairu\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;

class Fairu
{

    public \Illuminate\Http\Client\PendingRequest $client;

    protected ?array $credentials = null;

    public function __construct(?string $connection = 'default')
    {
        $credentials = config('fairu.connections.' . $connection);

        $this->credentials = $credentials;

        $this->client = Http::withHeaders([
            'Tenant' => data_get($credentials, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($credentials, 'tenant_secret'),
        ]);
    }

    protected function endpoint(string $path)
    {
        $url = config('fairu.url') . "/$path";
        Log::debug($url);
        return $url;
    }

    public function getFiles(?array $ids = []): ?array
    {
        $ids = array_filter($ids);

        if (empty($ids)) {
            return null;
        }

        $result = $this->client->post($this->endpoint('api/files/list'), [
            'ids' => $ids,
        ]);

        if ($result->status() != 200) {
            throw new Exception(json_encode($result?->json()));
        }

        return $result->json();
    }

    public function getScopeFromEndpoint(): ?array
    {

        $result = $this->client->get($this->endpoint('api/users/scope'));

        if ($result->status() != 200) {
            throw new Exception(json_encode($result?->json()));
        }

        return $result->json();
    }

    public function createFolder(array $folder): ?array
    {

        $result = $this->client->post($this->endpoint('api/folders'), $folder);

        if ($result->status() != 200) {
            throw new Exception(json_encode($result?->json()));
        }

        return $result?->json();
    }

    public function createFile(array $file): ?array
    {

        $result = $this->client->post($this->endpoint('api/files'), $file);

        if ($result->status() != 200) {
            throw new Exception(json_encode($result?->json()));
        }

        return $result?->json();
    }

    public function convertToUuid(string $str): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, data_get($this->credentials, 'tenant') . $str)->toString();
    }

    public function parse(string|array $str, ?string $container = null)
    {
        if (is_array($str)) {
            return array_map(function ($strItem) use ($container) {
                if (Str::isUuid($strItem)) {
                    return $strItem;
                }

                return $this->resolveOldAssetPath($strItem, $container);
            }, $str);
        }

        if (Str::isUuid($str)) {
            return $str;
        }

        return $this->resolveOldAssetPath($str, $container);
    }

    public function resolveOldAssetPath(?string $value = null, ?string $container = null): ?string
    {
        $id = null;

        if ($value == null) {
            return $id;
        }

        if ($container == null) {

            $containers = (array) Cache::flexible('asset-containers', [120, 240], function () {
                return AssetContainer::all()?->pluck('handle')->toArray();
            });

            if (count($containers) == 1) {
                $disk = Cache::remember('asset-container-' . $containers[0], now()->addMinutes(15), function () use ($containers) {
                    $container = FacadesAssetContainer::findByHandle($containers[0]);
                    return $container->disk;
                });
                $id = $this->convertToUuid(Storage::disk($disk)->url($value));
            }
        } else {

            $disk = Cache::remember('asset-container-' . $container, now()->addMinutes(60), function () use ($container) {
                $container = FacadesAssetContainer::findByHandle($container);
                return $container->disk;
            });
            $id = $this->convertToUuid(Storage::disk($disk)->url($value));
        }

        return $id;
    }
}
