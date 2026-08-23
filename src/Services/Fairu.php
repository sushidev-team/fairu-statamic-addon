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

    public function getFilesMeta(?array $ids = []): ?array
    {
        $ids = array_filter($ids);

        if (empty($ids)) {
            return null;
        }

        $result = $this->client->post($this->endpoint('api/files/meta'), [
            'ids' => $ids,
        ]);

        if ($result->status() != 200) {
            throw new Exception(json_encode($result?->json()));
        }

        return $result->json();
    }

    public function getExistingFileIds(array $ids, int $chunkSize = 200): array
    {
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return [];
        }

        $existing = [];

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            try {
                $response = $this->getFiles($chunk);
            } catch (Throwable $ex) {
                Log::warning('Fairu: getExistingFileIds chunk failed: ' . $ex->getMessage());
                continue;
            }

            $items = data_get($response, 'data', $response) ?? [];

            foreach ((array) $items as $item) {
                $id = data_get($item, 'id');
                if (is_string($id) && $id !== '') {
                    $existing[] = $id;
                }
            }
        }

        return array_values(array_unique($existing));
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
            Log::error('Fairu createFile failed for ' . data_get($file, 'filename') . ': ' . $ex->getMessage());
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

            $containers = (array) Cache::flexible(FairuCache::key('asset-containers'), [120, 240], function () {
                return AssetContainer::all()?->pluck('handle')->toArray();
            });

            if (count($containers) == 1) {
                $disk = Cache::remember(FairuCache::key('asset-container-' . $containers[0]), now()->addMinutes(15), function () use ($containers) {
                    $container = FacadesAssetContainer::findByHandle($containers[0]);
                    return $container->disk;
                });
                $id = $this->convertToUuid(Storage::disk($disk)->url($value));
            }
        } else {

            $disk = Cache::remember(FairuCache::key('asset-container-' . $container), now()->addMinutes(60), function () use ($container) {
                $container = FacadesAssetContainer::findByHandle($container);
                return $container->disk;
            });
            $id = $this->convertToUuid(Storage::disk($disk)->url($value));
        }

        return $id;
    }

    /**
     * Run a GraphQL document against the workspace.
     *
     * The REST endpoints answer in two fixed shapes; GraphQL is how the addon
     * asks for the shapes it actually renders — a gallery with its cover and
     * first twelve items in one round trip rather than three.
     *
     * `errors` comes back with a 200, so it has to be read rather than left to
     * the status code. Raised as an exception because every caller either has a
     * cache to fall back to or a template that would otherwise render a
     * confident empty state over a broken connection.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = []): array
    {
        $response = $this->client->post($this->endpoint('graphql'), [
            'query' => $query,
            'variables' => (object) $variables,
        ]);

        $json = $response->json() ?? [];

        if ($errors = data_get($json, 'errors')) {
            throw new Exception('Fairu GraphQL: ' . (data_get($errors, '0.message') ?? json_encode($errors)));
        }

        if ($response->status() != 200) {
            throw new Exception(json_encode($json));
        }

        return (array) (data_get($json, 'data') ?? []);
    }

    public function createUploadLink(string $filename, ?string $folder): ?array
    {
        $data = $this->graphql(<<<'GRAPHQL'
            mutation CreateUploadLink($filename: String!, $folder: ID) {
                createFairuUploadLink(filename: $filename, type: STANDARD, folder: $folder) {
                    id
                    mime
                    upload_url
                    sync_url
                }
            }
        GRAPHQL, [
            'filename' => $filename,
            'folder' => $folder,
        ]);

        return data_get($data, 'createFairuUploadLink', []);
    }

    /**
     * Purge the delivery cache for up to 50 files.
     *
     * The other half of the addon's cache story: FairuCache drops what this
     * site remembers about a file, this drops what the proxy and everything
     * downstream of it remember of the bytes. Needs `cache::purge` on the API
     * key — a key made before the permission existed does not carry it.
     *
     * @param  array<int, string>  $ids
     * @return array{queued: array<int, string>, missing: array<int, string>}
     */
    public function purgeCache(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return ['queued' => [], 'missing' => []];
        }

        $data = $this->graphql(<<<'GRAPHQL'
            mutation PurgeFairuCache($ids: [ID!]!) {
                purgeFairuCache(ids: $ids) {
                    queued
                    missing
                }
            }
        GRAPHQL, ['ids' => $ids]);

        return [
            'queued' => (array) (data_get($data, 'purgeFairuCache.queued') ?? []),
            'missing' => (array) (data_get($data, 'purgeFairuCache.missing') ?? []),
        ];
    }

    public function uploadFile(string $content, string $filename, ?string $folder): ?string
    {
        try {
            $uploadLink = $this->createUploadLink($filename, $folder);
        } catch (Throwable $ex) {
            Log::error('Error while uploading ' . $filename . ': ' . $ex->getMessage());

            return null;
        }

        $uploadUrl = data_get($uploadLink, 'upload_url');

        // No usable upload link (GraphQL error or empty response) — bail out
        // instead of sending a PUT to a null URL.
        if (empty($uploadUrl)) {
            Log::error('Error while uploading ' . $filename . ': no upload URL returned');

            return null;
        }

        try {
            $resultUpload = Http::withHeaders([
                'x-amz-acl'    => 'public-read',
                'Content-Type' => data_get($uploadLink, 'mime'),
            ])->send('PUT', $uploadUrl, [
                'body' => $content,
            ]);
        } catch (Throwable $ex) {
            Log::error('Error while uploading ' . $filename . ': ' . $ex->getMessage());

            return null;
        }

        if ($resultUpload->status() != 200) {
            Log::error('Error while uploading ' . $filename . ': upload responded with status ' . $resultUpload->status());

            return null;
        }

        try {
            $resultSync = Http::get(data_get($uploadLink, 'sync_url'));
        } catch (Throwable $ex) {
            Log::error('Error while uploading ' . $filename . ': sync failed: ' . $ex->getMessage());

            return null;
        }

        if (! $resultSync->successful()) {
            Log::error('Error while uploading ' . $filename . ': sync responded with status ' . $resultSync->status());

            return null;
        }

        return data_get($uploadLink, 'id');
    }
}
