<?php

namespace Sushidev\Fairu\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Sushidev\Fairu\Services\Import as ServicesImport;
use Statamic\Fieldtypes\Bard as FieldtypesBard;
use Statamic\Fieldtypes\Bard\Augmentor;

use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

use Ramsey\Uuid\Uuid;
use Statamic\Contracts\Assets\Asset as AssetsAsset;
use Throwable;

class Setup extends Command
{
    use RunsInPlease;

    protected $signature = 'fairu:setup';

    protected $description = 'Step by step wizard to bring your files to fairu';

    protected ?array $credentials = null;
    protected ?string $connection = null;

    public function handle(): void
    {
        try {
            $this->checkConnection();
        } catch (Throwable $ex) {
            $this->error($ex->getMessage());
            exit;
        }
        $this->importFiles();
    }

    protected function checkConnection(): void
    {

        $connection = select(
            label: 'What connection do you want to use?',
            options: array_keys(config('statamic.fairu.connections')),
            default: 'default'
        );

        $this->credentials = config('statamic.fairu.connections.' . $connection);
        $this->connection = $connection;

        $result = (new ServicesFairu($connection))->getScopeFromEndpoint();

        if ($result == null) {
            throw new Error('Cannot check the given credentials. Please make sure you have set the environment keys "FAIRU_TENANT" and "FAIRU_TENANT_SECRET" or that you defined your custom connection in the configuration.');
        }

        if (count($result) == 0) {
            throw new Error('Cannot check the given credentials. Please make sure you have set the environment keys "FAIRU_TENANT" and "FAIRU_TENANT_SECRET" or that you defined your custom connection in the configuration.');
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

        if ($containers == null) {
            throw new Error('Error while loading statamic containers. Please check if there has been a asset container defined.');
        }

        $assetContainer = select(
            label: 'What container do you want to import?',
            options: $containers,
            default: Arr::first($containers),
        );

        $assets = Asset::whereContainer($assetContainer);
        $folderList = FacadesAssetContainer::find($assetContainer)?->folders();

        if ($assets?->count() == 0){
            $this->error('No assets found.');
            return;
        }

        $paths = collect([]);

        // 1) Create the list of files 
        //    This step is important because otherwise we will not be
        //    able to push the file into correct fairu folder.
        progress(
            label: 'Prepare list of files...',
            steps: $assets?->count() > 0 ? $assets : [],
            callback: function ($asset, $progress) use (&$paths) {
                $paths->push($asset->path);
            },
            hint: 'This may take some time.'
        );

        // 2) Create folder list
        $folders = spin(
            message: 'Create list of folders...',
            callback: fn() => (new ServicesImport)->buildFlatFolderListByFolderArray($folderList->toArray())
        );

        try {

            // 3) Create folder entries in fairu
            progress(
                label: 'Creating folders in fairu...',
                steps: $folders,
                callback: function ($folder, $progress) {
                    (new ServicesFairu($this->connection))->createFolder($folder);
                },
                hint: 'This may take some time, because we are creating this folders in fairu.'
            );
        } catch (Throwable $ex) {
            $this->error("Seems like there is an error while creating the folders: " . $ex->getMessage());
        }

        // 4) Upload files
        $list = [];
        progress(
            label: 'Upload files to fairu...',
            steps: $assets,
            callback: function ($asset, $progress) use ($folders, &$list) {
                $uuid = (new ServicesFairu($this->connection))->convertToUuid($asset->url());
                $this->importAssetToFairu($asset, $uuid, $folders, $progress);

                $list[] = [
                    'id' => $asset->id,
                    'path' => $asset->path,
                    'fairu' => $uuid,
                    'url' => $asset->url(),
                ];
            },
            hint: 'This may take some time, because we are uploading the files to fairu.'
        );

        // 5) Replace

        $replace = confirm(
            label: 'Do you want to replace the files in blueprint, fieldset, entries & co. ?',
            default: false,
            yes: 'Yes',
            no: 'No',
        );

        if ($replace == true) {
            $this->replaceFields();
        }

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

    protected function importAssetToFairu(AssetsAsset $asset, string $uuid, array $folders, $progress)
    {

        $folder = (new ServicesImport)->getFolderPath($asset->path);
        $folderId = data_get(collect($folders)->where('path', $folder)->first(), 'id');

        $fileContent = $asset->contents();

        $fairuAssetEntry = [
            'id' => $uuid,
            'folder' => $folderId,
            'type' => 'standard',
            'filename' => $asset->basename(),
            'alt' => data_get($asset->data(), 'alt'),
            'focal_point' => data_get($asset->data(), 'focus'),
            'copyright' => data_get($asset->data(), config('statamic.fairu.migration.copyright'))
        ];

        $caption = Str::replace(["\n", '<br>', "\r", "\t", '|', ''], '', (new Augmentor(new FieldtypesBard))->augment(data_get($asset->data(), config('statamic.fairu.migration.caption'))));
        $description = Str::replace(["\n", '<br>', "\r", "\t", '|', ''], '', (new Augmentor(new FieldtypesBard))->augment(data_get($asset->data(), config('statamic.fairu.migration.description'))));

        data_set($fairuAssetEntry, 'caption', $caption);
        data_set($fairuAssetEntry, 'description', $description);

        $progress
            ->label("Create file entry " . $asset->basename());

        $result = (new ServicesFairu($this->connection))->createFile($fairuAssetEntry);

        $progress
            ->label("Uploading " . $asset->basename());

        $response = Http::withHeaders([
            "x-amz-acl" => "public-read",
            'Content-Type' => $asset->mime_type,
        ])->withBody($fileContent, $asset->mime_type)->put(data_get($result, 'upload_url'));

        if ($response->status() == 200) {
            Http::get(data_get($result, 'sync_url'));
        }
    }

    public function replaceFields()
    {

        $directories = [
            base_path('resources/blueprints'),
            base_path('resources/fieldsets')
        ];

        $oldType = 'type: assets';
        $newType = 'type: fairu';

        foreach ($directories as $dir) {
            $files = glob($dir . '/*.yaml');

            foreach ($files as $file) {
                $content = file_get_contents($file);

                if (strpos($content, $oldType) !== false) {
                    $updatedContent = str_replace($oldType, $newType, $content);
                    $this->info("Replace file name $file");
                    file_put_contents($file, $updatedContent);
                }
            }
        }
    }

}
