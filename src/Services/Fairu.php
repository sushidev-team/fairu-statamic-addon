<?php 

namespace SushidevTeam\Fairu\Services;

use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;

class Fairu {

    public \Illuminate\Http\Client\PendingRequest $client;

    protected ?array $credentials = null;

    public function __construct(?string $connection = 'default')
    {
        $credentials = config('fairu.connections.'.$connection);

        $this->credentials = $credentials;

        $this->client = Http::withHeaders([
            'Tenant' => data_get($credentials, 'tenant'),
            'Authorization' => 'Bearer '.data_get($credentials, 'tenant_secret'),
        ]);
    }

    protected function endpoint(string $path){
        return config('fairu.url') . "/$path";
    }
    
    public function getScopeFromEndpoint(): ?array {

        $result = $this->client->get($this->endpoint('api/users/scope'));
        return $result->json();

    }

    public function convertToUuid(string $str): string{
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, data_get($this->credentials, 'tenant') . $str)->toString();
    }

}