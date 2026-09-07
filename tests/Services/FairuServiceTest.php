<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\AssetContainer;
use Sushidev\Fairu\Services\Fairu;
use Sushidev\Fairu\Services\Import;

it('skips API calls for empty file id lists', function ($method) {
    expect((new Fairu())->$method([]))->toBeNull();
    Http::assertNothingSent();
})->with(['getFiles', 'getFilesMeta']);

it('handles service API success and failure responses', function ($method, $argument, $status) {
    Http::fake(['*' => Http::response(['id' => 'result'], $status)]);
    $service = new Fairu();
    if ($status !== 200 && $method !== 'createFile') {
        expect(fn () => $service->$method($argument))->toThrow(Exception::class);
    } else {
        expect($service->$method($argument))->toBe($status === 200 ? ['id' => 'result'] : null);
    }
})->with([
    ['getFiles', ['id']], ['getFilesMeta', ['id']], ['getScopeFromEndpoint', []],
    ['createFolder', ['name' => 'Folder']], ['createFile', ['filename' => 'image.webp']],
])->with([200, 500]);

it('collects existing ids across chunks and tolerates failed chunks', function () {
    Http::fake(['*' => Http::sequence()->push(['data' => [['id' => 'a'], ['id' => 'a'], ['name' => 'invalid']]])->push([], 500)->push([['id' => 'c']])]);
    $service = new Fairu();
    expect($service->getExistingFileIds([]))->toBe([])
        ->and($service->getExistingFileIds(['a', 'b', 'c'], 1))->toBe(['a', 'c']);
    Http::assertSentCount(3);
});

it('resolves legacy assets using explicit or single containers', function ($explicit) {
    Storage::fake('legacy');
    $container = AssetContainer::make('legacy')->disk('legacy');
    AssetContainer::shouldReceive('findByHandle')->with('legacy')->andReturn($container);
    AssetContainer::shouldReceive('all')->andReturn(collect([$container]));
    Cache::put('asset-containers', ['legacy']);
    $service = new Fairu();
    $id = $service->convertToUuid(Storage::disk('legacy')->url('image.webp'));
    expect($service->parse('image.webp', $explicit ? 'legacy' : null))->toBe($id)
        ->and($service->parse([TEMPLATE_ID, 'image.webp'], $explicit ? 'legacy' : null))->toBe([TEMPLATE_ID, $id])
        ->and($service->parse(null))->toBeNull()
        ->and($service->resolveOldAssetPath())->toBeNull();
})->with([false, true]);

it('does not guess a legacy container when several exist', function () {
    Cache::put('asset-containers', ['a', 'b']);
    expect((new Fairu())->resolveOldAssetPath('image.webp'))->toBeNull();
});

it('builds deterministic folder trees and preserves parent links', function () {
    $import = new Import();
    $folders = $import->buildFlatFolderListByFolderArray(['photos/events', 'photos', '', 'photos/events']);
    expect(array_column($folders, 'path'))->toBe(['photos', 'photos/events'])
        ->and($folders[1]['parent_id'])->toBe($folders[0]['id'])
        ->and($import->buildFlatFolderListByFolderArray(['photos'])[0]['id'])->toBe($folders[0]['id']);
    $files = $import->buildFlatFolderList(['photos/events/a.webp', 'photos/events/b.webp', 'file.webp']);
    expect($files)->toHaveCount(2)->and($files[1]['parent_id'])->toBe($files[0]['id'])
        ->and($import->getFolderPath('photos/a.webp'))->toBe('photos')
        ->and($import->getFolderPath('file.webp'))->toBe('');
});
