<?php

namespace Sushidev\Fairu\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer as FacadesAssetContainer;
use Sushidev\Fairu\Commands\Concerns\UploadsAssetsToFairu;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Sushidev\Fairu\Services\Import as ServicesImport;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class Sync extends Command
{
    use RunsInPlease;
    use UploadsAssetsToFairu;

    protected $signature = 'fairu:sync {--connection=} {--container=}';

    protected $description = 'Upload assets that were added to Statamic after the initial fairu:setup run.';

    protected ?string $connection = null;

    public function handle(): void
    {
        try {
            $this->resolveConnection();
        } catch (Throwable $ex) {
            error($ex->getMessage());
            exit;
        }

        try {
            $this->syncContainer();
        } catch (Throwable $ex) {
            error($ex->getMessage());
            exit;
        }
    }

    protected function resolveConnection(): void
    {
        $connections = array_keys((array) config('statamic.fairu.connections'));

        $connection = $this->option('connection') ?: (count($connections) === 1
            ? $connections[0]
            : select(
                label: 'What connection do you want to use?',
                options: $connections,
                default: in_array('default', $connections, true) ? 'default' : Arr::first($connections),
            ));

        if (!in_array($connection, $connections, true)) {
            throw new Error(sprintf('Unknown connection "%s". Available: %s', $connection, implode(', ', $connections)));
        }

        $this->connection = $connection;

        $scope = (new ServicesFairu($connection))->getScopeFromEndpoint();

        if ($scope == null || count($scope) === 0) {
            throw new Error('Cannot verify Fairu credentials. Ensure FAIRU_TENANT and FAIRU_TENANT_SECRET are set, or that your custom connection is configured.');
        }
    }

    protected function syncContainer(): void
    {
        $containers = FacadesAssetContainer::all()?->pluck('handle')->toArray();

        if (empty($containers)) {
            throw new Error('No Statamic asset containers found.');
        }

        $assetContainer = $this->option('container') ?: select(
            label: 'What container do you want to sync?',
            options: $containers,
            default: Arr::first($containers),
        );

        if (!in_array($assetContainer, $containers, true)) {
            throw new Error(sprintf('Unknown container "%s". Available: %s', $assetContainer, implode(', ', $containers)));
        }

        $assets = Asset::whereContainer($assetContainer);
        $folderList = FacadesAssetContainer::find($assetContainer)?->folders();

        if ($assets === null || $assets->count() === 0) {
            info(sprintf('No assets found in container "%s". Nothing to sync.', $assetContainer));
            return;
        }

        $fairuService = new ServicesFairu($this->connection);

        $uuidMap = [];
        foreach ($assets as $asset) {
            $uuidMap[$asset->id()] = $fairuService->convertToUuid($asset->url());
        }

        $existingIds = spin(
            message: 'Checking which files already exist on fairu...',
            callback: fn() => $fairuService->getExistingFileIds(array_values($uuidMap)),
        );
        $existingIdSet = array_flip($existingIds);

        if (count($existingIds) === 0) {
            throw new Error(sprintf(
                'None of the %d asset(s) in container "%s" were found on fairu. Run `php please fairu:setup` first to perform the initial import.',
                count($uuidMap),
                $assetContainer,
            ));
        }

        $missingAssets = [];
        foreach ($assets as $asset) {
            $uuid = $uuidMap[$asset->id()] ?? null;
            if ($uuid === null || isset($existingIdSet[$uuid])) {
                continue;
            }
            $missingAssets[] = $asset;
        }

        if (count($missingAssets) === 0) {
            outro(sprintf(
                'All %d asset(s) in "%s" are already on fairu. Nothing to sync.',
                count($uuidMap),
                $assetContainer,
            ));
            return;
        }

        info(sprintf(
            '%d of %d asset(s) are missing on fairu and will be uploaded.',
            count($missingAssets),
            count($uuidMap),
        ));

        $folders = spin(
            message: 'Building folder list...',
            callback: fn() => (new ServicesImport)->buildFlatFolderListByFolderArray($folderList->toArray()),
        );

        progress(
            label: 'Ensuring folders exist in fairu...',
            steps: $folders,
            callback: function ($folder, $progress) {
                try {
                    (new ServicesFairu($this->connection))->createFolder($folder);
                } catch (Throwable $ex) {
                    Log::warning('Fairu: createFolder failed for "' . data_get($folder, 'path') . '": ' . $ex->getMessage());
                }
            },
            hint: 'Folder creation is idempotent — existing folders are left alone.',
        );

        $list = [];
        $failed = [];

        progress(
            label: 'Uploading missing files to fairu...',
            steps: $missingAssets,
            callback: function ($asset, $progress) use ($folders, $uuidMap, &$list, &$failed) {
                $assetPath = $asset->path ?? null;
                $entry = null;

                try {
                    $uuid = $uuidMap[$asset->id()] ?? (new ServicesFairu($this->connection))->convertToUuid($asset->url());

                    $entry = [
                        'id' => $asset->id,
                        'path' => $assetPath,
                        'fairu' => $uuid,
                        'url' => $asset->url(),
                        'asset' => $asset,
                    ];

                    $success = $this->importAssetToFairu($asset, $uuid, $folders, $progress);
                } catch (Throwable $ex) {
                    Log::error(sprintf(
                        'Fairu: unexpected error importing %s: %s%s%s',
                        $assetPath ?? 'unknown asset',
                        $ex->getMessage(),
                        PHP_EOL,
                        $ex->getTraceAsString(),
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
            hint: 'Only uploading files that are not yet on fairu.',
        );

        $this->reportUploadResults($list, $failed);
        $this->retryFailedUploads($folders, $list, $failed);

        outro('Sync finished.');
    }
}
