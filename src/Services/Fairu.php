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
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use Throwable;

class Fairu
{

    public \Illuminate\Http\Client\PendingRequest $client;

    protected ?array $credentials = null;

    public function __construct(?string $connection = 'default')
    {
        $credentials = config('statamic.fairu.connections.' . $connection);

        $this->credentials = $credentials;

        $this->client = Http::withHeaders([
            'Tenant' => data_get($credentials, 'tenant'),
            'Authorization' => 'Bearer ' . data_get($credentials, 'tenant_secret'),
        ]);
    }

    protected function endpoint(string $path)
    {
        $url = config('statamic.fairu.url') . "/$path";
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
        try {

            $result = $this->client->post($this->endpoint('api/files'), $file);

            if ($result->status() != 200) {
                throw new Exception(json_encode($result?->json()));
            }

            return $result?->json();
        } catch (Throwable $ex) {
            return null;
        }
    }

    public function convertToUuid(string $str): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, data_get($this->credentials, 'tenant') . $str)->toString();
    }

    public function parse($str, ?string $container = null)
    {
        if ($str == null) {
            return null;
        }

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

    public function createUploadLink(string $filename, ?string $folder): ?array
    {
        $response = $this->client->post($this->endpoint('graphql'), [
            'query' => <<<'GRAPHQL'
                mutation CreateUploadLink($filename: String!, $folder: ID) {
                    createFairuUploadLink(filename: $filename, type: STANDARD, folder: $folder) {
                        id
                        mime
                        upload_url
                        sync_url
                    }
                }
            GRAPHQL,
            'variables' => [
                'filename' => $filename,
                'folder' => $folder,
            ],
        ]);

        return data_get($response->json(), 'data.createFairuUploadLink', []);
    }

    public function uploadFile(string $content, string $filename, ?string $folder): ?bool
    {
        $success = false;
        $uploadLink = $this->createUploadLink($filename, $folder);

        $resultUpload = Http::withHeaders([
            'x-amz-acl'    => 'public-read',
            'Content-Type' => data_get($uploadLink, 'mime'),
        ])->put(
            data_get($uploadLink, 'upload_url'),
            $content,
        );

        if ($resultUpload->ok()) {
            Http::get(data_get($uploadLink, 'sync_url'));
            $success = true;
        }

        return $success;
    }
}
