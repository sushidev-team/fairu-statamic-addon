<?php

namespace Sushidev\Fairu\Commands\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Contracts\Assets\Asset as AssetsAsset;
use Statamic\Fieldtypes\Bard as FieldtypesBard;
use Statamic\Fieldtypes\Bard\Augmentor;
use Sushidev\Fairu\Services\Fairu as ServicesFairu;
use Sushidev\Fairu\Services\Import as ServicesImport;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

trait UploadsAssetsToFairu
{
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

        try {
            $value = data_get($asset->data(), $handle);

            if ($value === null || $value === '') {
                return null;
            }

            $augmented = (new Augmentor(new FieldtypesBard))->augment($value);

            if (!is_string($augmented)) {
                return is_string($value) ? $value : null;
            }

            return Str::replace(["\n", '<br>', "\r", "\t", '|'], '', $augmented);
        } catch (Throwable $ex) {
            Log::warning('Fairu: Bard augmentation failed for ' . $asset->path() . ' field "' . $handle . '": ' . $ex->getMessage());
            return null;
        }
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

                    try {
                        $success = $this->importAssetToFairu($asset, $uuid, $folders, $progress);
                    } catch (Throwable $ex) {
                        Log::error('Fairu: unexpected error retrying ' . $asset->path() . ': ' . $ex->getMessage());
                        $success = false;
                    }

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
}
