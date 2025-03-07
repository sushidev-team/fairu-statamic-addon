<?php

namespace SushidevTeam\Fairu\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use SushidevTeam\Fairu\Services\Fairu as ServicesFairu;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

use Ramsey\Uuid\Uuid;
use Statamic\Contracts\Assets\Asset as AssetsAsset;

class Setup extends Command
{
    use RunsInPlease;

    protected $signature = 'fairu:setup';

    protected $description = 'Step by step wizard to bring your files to fairu';

    protected ?array $credentials = null;
    protected ?string $connection = null;

    public function handle(): void
    {
        $this->checkConnection();
        $this->importFiles();
    }

    protected function checkConnection(): void
    {

        $connection = select(
            label: 'What connection do you want to use?',
            options: array_keys(config('fairu.connections')),
            default: 'default'
        );

        $this->credentials = config('fairu.connections.' . $connection);
        $this->connection = $connection;

        $result = (new ServicesFairu($connection))->getScopeFromEndpoint();

        if ($result == null) {
            $this->error('Cannot check the given credentials. Please make sure you have set the environment keys "FAIRU_TENANT" and "FAIRU_TENANT_SECRET" or that you defined your custom connection in the configuration.');
        }

        // Output some information about the keys
        $this->info("Your api keys are valid and we could recieve the following user data associated with the api.");
        $this->table(['id', 'email'], [Arr::only($result, ['id', 'email'])]);

        $continue = confirm("Want to continue with the import of your files to fairu?");

        if ($continue == false) {
            exit;
        }
    }

    protected function importFiles()
    {

        $containers = FacadesAssetContainer::all()?->pluck('handle')->toArray();

        $assetContainer = select(
            label: 'What container do you want to import?',
            options: $containers,
            default: Arr::first($containers),
        );

        $assets = Asset::whereContainer($assetContainer);

        $assets->each(function ($asset) {
            $uuid = (new ServicesFairu($this->connection))->convertToUuid($asset->id);
            $this->importAssetToFairu($asset, $uuid);
        });

        $restart = confirm(
            label: 'Do you want to import another container?',
            default: false,
            yes: 'Yes',
            no: 'No',
        );

        if ($restart == false) {
            $this->info("Setup for has been finished. Open https://docs.fairu.app/docs/statamic for further instructions.");
            return;
        }

        return $this->importFiles();
    }

    protected function importAssetToFairu(AssetsAsset $asset, $uuid)
    {
        // TODO: Write import logic
    }
}
