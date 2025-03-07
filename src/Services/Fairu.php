<?php 

namespace SushidevTeam\Fairu\Services;

use Illuminate\Support\Facades\Http;

class Fairu {

    public \Illuminate\Http\Client\PendingRequest $client;

    public function __construct(?string $connection = 'default')
    {
        $credentials = config('fairu.connections.'.$connection);

        $this->client = Http::withHeaders([
            'Tenant' => data_get($credentials, 'tenant'),
            'Authorization' => 'Bearer '.data_get($credentials, 'tenant_secret'),
        ]);
    }

    protected function endpoint(string $path){
        return config('fairu.url') . "/$path";
    }
    
    public function scope(): ?array {

        $result = $this->client->get($this->endpoint('api/users/scope'));
        return $result->json();

    }

}