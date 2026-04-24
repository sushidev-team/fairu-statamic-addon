<?php

namespace Sushidev\Fairu\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use Statamic\Facades\Blueprint as FacadesBlueprint;
use Statamic\Facades\Fieldset as FacadesFieldset;
use Sushidev\Fairu\Commands\Concerns\UploadsAssetsToFairu;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Sushidev\Fairu\Services\Import as ServicesImport;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

use Throwable;

class Setup extends Command
{
    use RunsInPlease;
    use UploadsAssetsToFairu;

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

        // 3) Create folder entries in fairu (per-folder fault tolerance)
        progress(
            label: 'Creating folders in fairu...',
            steps: $folders,
            callback: function ($folder, $progress) {
                try {
                    (new ServicesFairu($this->connection))->createFolder($folder);
                } catch (Throwable $ex) {
                    Log::warning('Fairu: createFolder failed for "' . data_get($folder, 'path') . '": ' . $ex->getMessage());
                }
            },
            hint: 'This may take some time, because we are creating this folders in fairu.'
        );

        // 4) Check which files already exist on fairu so we can resume
        $uuidMap = [];
        $fairuService = new ServicesFairu($this->connection);

        foreach ($assets as $asset) {
            $uuidMap[$asset->id()] = $fairuService->convertToUuid($asset->url());
        }

        $existingIds = spin(
            message: 'Checking which files already exist on fairu...',
            callback: fn() => $fairuService->getExistingFileIds(array_values($uuidMap))
        );
        $existingIdSet = array_flip($existingIds);

        if (count($existingIds) > 0) {
            info(sprintf('%d of %d file(s) already exist on fairu and will be skipped.', count($existingIds), count($uuidMap)));
        }

        // 5) Upload files
        $list = [];
        $failed = [];
        $skipped = [];
        progress(
            label: 'Upload files to fairu...',
            steps: $assets,
            callback: function ($asset, $progress) use ($folders, $uuidMap, $existingIdSet, &$list, &$failed, &$skipped) {
                $assetPath = null;
                $entry = null;

                try {
                    $assetPath = $asset->path ?? null;
                    $uuid = $uuidMap[$asset->id()] ?? (new ServicesFairu($this->connection))->convertToUuid($asset->url());

                    $entry = [
                        'id' => $asset->id,
                        'path' => $assetPath,
                        'fairu' => $uuid,
                        'url' => $asset->url(),
                        'asset' => $asset,
                    ];

                    if (isset($existingIdSet[$uuid])) {
                        $progress->label('Skipping (already on fairu) ' . $asset->basename());
                        $skipped[] = $entry;
                        $list[] = $entry;
                        return;
                    }

                    $success = $this->importAssetToFairu($asset, $uuid, $folders, $progress);
                } catch (Throwable $ex) {
                    Log::error(sprintf(
                        'Fairu: unexpected error importing %s: %s%s%s',
                        $assetPath ?? 'unknown asset',
                        $ex->getMessage(),
                        PHP_EOL,
                        $ex->getTraceAsString()
                    ));
                    $success = false;
                    if ($entry === null) {
                        $entry = [
                            'id' => null,
                            'path' => $assetPath,
                            'fairu' => null,
                            'url' => null,
                            'asset' => $asset,
                        ];
                    }
                }

                if ($success) {
                    $list[] = $entry;
                } else {
                    $failed[] = $entry;
                }
            },
            hint: 'This may take some time, because we are uploading the files to fairu.'
        );

        if (count($skipped) > 0) {
            info(sprintf('%d file(s) skipped (already present on fairu).', count($skipped)));
        }

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

    public function replaceFields()
    {
        $oldType = 'type: assets';
        $newType = 'type: fairu';

        $directories = array_values(array_unique(array_filter([
            FacadesBlueprint::directory(),
            FacadesFieldset::directory(),
            base_path('resources/blueprints'),
            base_path('resources/fieldsets'),
            base_path('content/globals'),
        ])));

        $summary = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $scanned = 0;
            $changed = 0;
            $occurrences = 0;

            $finder = (new Finder)->files()->in($dir)->name('*.yaml');

            foreach ($finder as $file) {
                $scanned++;
                $path = $file->getRealPath();
                $content = file_get_contents($path);
                $count = substr_count($content, $oldType);

                if ($count === 0) {
                    continue;
                }

                file_put_contents($path, str_replace($oldType, $newType, $content));
                $changed++;
                $occurrences += $count;
                note("Replaced file: $path ($count)");
            }

            $summary[] = [
                'directory' => $dir,
                'scanned' => $scanned,
                'changed' => $changed,
                'replacements' => $occurrences,
            ];
        }

        table(['Directory', 'Scanned', 'Changed', 'Replacements'], $summary);
    }
}
