<?php

namespace Sushidev\Fairu\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Sushidev\Fairu\Services\Import as ServicesImport;
use Statamic\Fieldtypes\Bard as FieldtypesBard;
use Statamic\Fieldtypes\Bard\Augmentor;

use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

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
            error($ex->getMessage());
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
        info('Your api keys are valid and we could receive the following user data associated with the api.');
        table(['id', 'email'], [Arr::only($result, ['id', 'email'])]);

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

        if ($assets?->count() == 0) {
            error('No assets found.');
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
            error('Seems like there is an error while creating the folders: ' . $ex->getMessage());
        }

        // 4) Upload files
        $list = [];
        $failed = [];
        progress(
            label: 'Upload files to fairu...',
            steps: $assets,
            callback: function ($asset, $progress) use ($folders, &$list, &$failed) {
                $uuid = (new ServicesFairu($this->connection))->convertToUuid($asset->url());
                $success = $this->importAssetToFairu($asset, $uuid, $folders, $progress);

                $entry = [
                    'id' => $asset->id,
                    'path' => $asset->path,
                    'fairu' => $uuid,
                    'url' => $asset->url(),
                    'asset' => $asset,
                ];

                if ($success) {
                    $list[] = $entry;
                } else {
                    $failed[] = $entry;
                }
            },
            hint: 'This may take some time, because we are uploading the files to fairu.'
        );

        $this->reportUploadResults($list, $failed);
        $this->retryFailedUploads($folders, $list, $failed);

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
            outro('Setup has been finished. Open https://docs.fairu.app/docs/statamic for further instructions.');
            return;
        }

        return $this->importFiles();
    }

    protected function importAssetToFairu(AssetsAsset $asset, string $uuid, array $folders, $progress): bool
    {

        $folder = (new ServicesImport)->getFolderPath($asset->path);
        $folderId = data_get(collect($folders)->where('path', $folder)->first(), 'id');

        $fileContent = $asset->contents();
        $mimeType = $asset->mimeType() ?: 'application/octet-stream';

        $fairuAssetEntry = [
            'id' => $uuid,
            'folder' => $folderId,
            'type' => 'standard',
            'filename' => $asset->basename(),
            'alt' => data_get($asset->data(), 'alt'),
            'focal_point' => data_get($asset->data(), 'focus'),
            'copyright' => data_get($asset->data(), config('statamic.fairu.migration.copyright'))
        ];

        data_set($fairuAssetEntry, 'caption', $this->augmentBardField($asset, config('statamic.fairu.migration.caption')));
        data_set($fairuAssetEntry, 'description', $this->augmentBardField($asset, config('statamic.fairu.migration.description')));

        $progress->label("Create file entry " . $asset->basename());

        $result = (new ServicesFairu($this->connection))->createFile($fairuAssetEntry);

        if ($result == null) {
            $progress->label("Failed uploading " . $asset->basename());
            Log::warning('Fairu: createFile returned null for ' . $asset->path());
            return false;
        }

        $progress->label("Uploading " . $asset->basename());

        try {
            $response = Http::withHeaders([
                'x-amz-acl' => 'public-read',
            ])->send('PUT', data_get($result, 'upload_url'), [
                'body' => $fileContent,
                'headers' => ['Content-Type' => $mimeType],
            ]);
        } catch (Throwable $ex) {
            Log::error('Fairu: S3 upload failed for ' . $asset->path() . ': ' . $ex->getMessage());
            return false;
        }

        if (!$response->successful()) {
            Log::error(sprintf(
                'Fairu: S3 upload returned status %d for %s. Body: %s',
                $response->status(),
                $asset->path(),
                substr((string) $response->body(), 0, 500)
            ));
            return false;
        }

        $syncResponse = Http::get(data_get($result, 'sync_url'));

        if (!$syncResponse->successful()) {
            Log::error(sprintf(
                'Fairu: sync_url returned status %d for %s',
                $syncResponse->status(),
                $asset->path()
            ));
            return false;
        }

        return true;
    }

    protected function augmentBardField(AssetsAsset $asset, ?string $handle): ?string
    {
        if ($handle === null) {
            return null;
        }

        $value = data_get($asset->data(), $handle);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            $augmented = (new Augmentor(new FieldtypesBard))->augment($value);
        } catch (Throwable $ex) {
            Log::warning('Fairu: Bard augmentation failed for ' . $asset->path() . ' field "' . $handle . '": ' . $ex->getMessage());
            return is_string($value) ? $value : null;
        }

        if (!is_string($augmented)) {
            return null;
        }

        return Str::replace(["\n", '<br>', "\r", "\t", '|'], '', $augmented);
    }

    protected function reportUploadResults(array $list, array $failed): void
    {
        if (count($failed) === 0) {
            info(sprintf('%d file(s) uploaded successfully.', count($list)));
            return;
        }

        warning(sprintf(
            '%d of %d file(s) failed to upload:',
            count($failed),
            count($list) + count($failed)
        ));
        table(
            ['path', 'url'],
            array_map(fn($f) => Arr::only($f, ['path', 'url']), $failed)
        );
        note('Check laravel.log for detailed error messages.');
    }

    protected function retryFailedUploads(array $folders, array &$list, array &$failed): void
    {
        while (count($failed) > 0) {

            $retry = confirm(
                label: sprintf('Retry uploading %d failed file(s)?', count($failed)),
                default: true,
                yes: 'Yes',
                no: 'No',
            );

            if ($retry === false) {
                return;
            }

            $toRetry = $failed;
            $failed = [];

            progress(
                label: 'Retrying failed uploads...',
                steps: $toRetry,
                callback: function ($entry, $progress) use ($folders, &$list, &$failed) {
                    $asset = data_get($entry, 'asset');
                    $uuid = data_get($entry, 'fairu');

                    if ($asset === null) {
                        $failed[] = $entry;
                        return;
                    }

                    $success = $this->importAssetToFairu($asset, $uuid, $folders, $progress);

                    if ($success) {
                        $list[] = $entry;
                    } else {
                        $failed[] = $entry;
                    }
                },
                hint: 'Retrying previously failed uploads.'
            );

            $this->reportUploadResults($list, $failed);
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
                    note("Replaced file: $file");
                    file_put_contents($file, $updatedContent);
                }
            }
        }
    }
}
