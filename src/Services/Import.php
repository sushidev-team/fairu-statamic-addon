<?php

namespace Sushidev\Fairu\Services;

use Illuminate\Support\Str;

class Import
{
    public function buildFlatFolderListByFolderArray(array $folderPaths, ?string $connection = 'default'): array
    {
        $folders = [];
        $uuids = [];

        usort($folderPaths, function ($a, $b) {
            return substr_count($a, '/') - substr_count($b, '/');
        });

        foreach ($folderPaths as $path) {
            $parts = explode('/', $path);
            $parentUuid = null;

            foreach ($parts as $index => $part) {
                $currentPath = implode('/', array_slice($parts, 0, $index + 1));

                if (!isset($uuids[$currentPath])) {
                    $uuid = (string) (new Fairu($connection))->convertToUuid($currentPath);
                    $folders[] = [
                        'name' => $part,
                        'id' => $uuid,
                        'parent_id' => $parentUuid,
                        'path' => $currentPath,
                    ];
                    $uuids[$currentPath] = $uuid;
                }

                $parentUuid = $uuids[$currentPath];
            }
        }

        return $folders;
    }

    public function buildFlatFolderList(array $filePaths): array
    {
        $folders = [];
        $uuids = [];

        sort($filePaths);

        foreach ($filePaths as $path) {
            $parts = explode('/', trim($path, '/'));
            array_pop($parts);

            $parentUuid = null;

            foreach ($parts as $part) {
                $currentPath = implode('/', array_slice($parts, 0, array_search($part, $parts) + 1));

                if (!isset($uuids[$currentPath])) {
                    $uuid = (string) Str::uuid();
                    $folders[] = [
                        'name' => $part,
                        'id' => $uuid,
                        'parent_id' => $parentUuid
                    ];
                    $uuids[$currentPath] = $uuid;
                }

                $parentUuid = $uuids[$currentPath];
            }
        }

        return $folders;
    }

    public function getFolderPath(string $path): string
    {
        return dirname($path) !== '.' ? dirname($path) : '';
    }
}
