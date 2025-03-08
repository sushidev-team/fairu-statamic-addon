<?php

namespace SushidevTeam\Fairu\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use SushidevTeam\Fairu\Services\Fairu as ServicesFairu;
use SushidevTeam\Fairu\Services\Import as ServicesImport;


use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

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
        $folderList = FacadesAssetContainer::find($assetContainer)?->folders();

        $paths = collect([]);

        // 1) Create the list of files 
        //    This step is important because otherwise we will not be
        //    able to push the file into correct fairu folder.
        progress(
            label: 'Prepare list of files...',
            steps: $assets,
            callback: function ($asset, $progress) use (&$paths) {
                $paths->push($asset->path);
            },
            hint: 'This may take some time.'
        );

        // 2) Create folder list
        $list = spin(
            message: 'Create list of folders...',
            callback: fn () => (new ServicesImport)->buildFlatFodlerListByFolderArray($folderList->toArray())
        );

        // 3) Create folder entries in fairu
        progress(
            label: 'Creating folders in fairu...',
            steps: $list,
            callback: function ($asset, $progress) {
                
            },
            hint: 'This may take some time.'
        );
        
        dd($list);

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
        $this->line($asset->path);
    }
}
