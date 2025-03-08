<?php

namespace SushidevTeam\Fairu\Services;

use Illuminate\Support\Str;

class Import
{
    public function buildFlatFodlerListByFolderArray(array $folderPaths): array {
        $folders = [];
        $uuids = [];
    
        foreach ($folderPaths as $path) {
            $parts = explode('/', $path);
            $parentUuid = null;
    
            foreach ($parts as $index => $part) {
                $currentPath = implode('/', array_slice($parts, 0, $index + 1));
    
                if (!isset($uuids[$currentPath])) {
                    $uuid = (string) Str::uuid();
                    $folders[] = [
                        'name' => $part,
                        'id' => $uuid,
                        'parent_id' => $parentUuid
                    ];
                    $uuids[$currentPath] = $uuid;
                }
    
                // Setze die Parent-UUID für den nächsten Unterordner
                $parentUuid = $uuids[$currentPath];
            }
        }
    
        return $folders;
    }

    public function buildFlatFolderList(array $filePaths): array
    {
        $folders = [];
        $uuids = [];

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
}
