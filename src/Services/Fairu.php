<?php

namespace Sushidev\Fairu\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\Asset;

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
        return config('fairu.url') . "/$path";
    }

    public function getFile(string $id): ?array
    {

        $result = $this->client->get($this->endpoint('api/files/' . $id));

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

    public function parse(string $str): ?string 
    {
        if (Str::isUuid($str)){
            return $str;
        }        

        return $this->resolveOldAssetPath($str);
    }

    protected function resolveOldAssetPath(string $value): ?string {

        $containers = AssetContainer::all()?->pluck('handle')->toArray();
        $id = null;

        foreach($containers as $container){
            $asset = Asset::whereContainer($container)->where('path',$value)?->first();
            if ($asset != null){
                $id = $this->convertToUuid($asset->url());
                break;
            }
        }

        return $id;
        
    }
}
