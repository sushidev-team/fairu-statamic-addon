<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Sushidev\Fairu\Commands\Setup;
use Sushidev\Fairu\Commands\Sync;
use Sushidev\Fairu\Services\Fairu;

function syncCommand(array $options = []): Sync
{
    $command = new Sync();
    $command->setLaravel(app());
    $command->setInput(new \Symfony\Component\Console\Input\ArrayInput($options, $command->getDefinition()));
    return $command;
}

it('reports invalid setup credentials without terminating PHP', function ($response) {
    fakeCommandPrompts(['default']);
    Http::fake(['*' => Http::response($response)]);
    expect((new Setup())->handle())->toBe(1);
})->with([[null], [[]]]);

it('allows cancelling setup before importing', function () {
    fakeCommandPrompts(['default', false]);
    fakeCommandApi();
    expect((new Setup())->handle())->toBe(0);
    Http::assertSentCount(1);
});

it('reports an empty setup container', function () {
    fakeCommandPrompts(['default', true, 'assets']);
    fakeCommandApi();
    mockCommandContainer([]);
    expect((new Setup())->handle())->toBe(0);
    Http::assertSentCount(1);
});

it('imports a container while tolerating folder errors and broken assets', function () {
    $old = commandAsset('photos/old.webp');
    $new = commandAsset('photos/new.webp');
    fakeCommandPrompts(['default', true, 'assets', false, false, false]);
    fakeCommandApi([(new Fairu())->convertToUuid($old->url())], true);
    // Preparing paths happens before the upload loop, so break only on the second call.
    // Use a concrete sequence on a separate asset to exercise a disappearing file.
    $broken = Mockery::mock(\Statamic\Contracts\Assets\Asset::class);
    $broken->shouldReceive('id')->andReturn('assets::broken');
    $broken->shouldReceive('url')->andReturn('/assets/broken');
    $broken->shouldReceive('path')->once()->andReturn('photos/broken.webp');
    $broken->shouldReceive('path')->andThrow(new RuntimeException('gone'));
    mockCommandContainer([$old, $new, $broken]);
    expect((new Setup())->handle())->toBe(0);
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->body() === 'image bytes');
});

it('can restart setup with another container and replace blueprint fields', function () {
    $asset = commandAsset('photos/new.webp');
    mockCommandContainer([$asset]);
    fakeCommandApi();
    fakeCommandPrompts(['default', true, 'assets', true, true, 'assets', false, false]);
    expect((new Setup())->handle())->toBe(0);
});

it('reports unknown connections and empty credentials during sync', function ($options, $emptyScope) {
    fakeCommandPrompts();
    Http::fake(['*' => Http::response($emptyScope ? [] : ['id' => 'user'])]);
    expect(syncCommand($options)->handle())->toBe(1);
})->with([[['--connection' => 'missing'], false], [[], true]]);

it('reports missing or unknown containers during sync', function ($unknown) {
    fakeCommandPrompts();
    fakeCommandApi();
    if ($unknown) {
        mockCommandContainer([]);
    } else {
        \Statamic\Facades\AssetContainer::shouldReceive('all')->andReturn(collect());
    }
    expect(syncCommand(['--container' => 'missing'])->handle())->toBe(1);
})->with([true, false]);

it('handles empty, uninitialized and already synchronized containers', function ($scenario) {
    fakeCommandPrompts(['assets']);
    $asset = commandAsset('photos/old.webp');
    mockCommandContainer($scenario === 'empty' ? [] : [$asset]);
    fakeCommandApi($scenario === 'synced' ? [(new Fairu())->convertToUuid($asset->url())] : []);
    expect(syncCommand()->handle())->toBe($scenario === 'uninitialized' ? 1 : 0);
})->with(['empty', 'uninitialized', 'synced']);

it('syncs missing files and handles broken files', function ($failure) {
    $old = commandAsset('photos/old.webp');
    $new = commandAsset('photos/new.webp', $failure === 'broken');
    mockCommandContainer([$old, $new]);
    fakeCommandApi([(new Fairu())->convertToUuid($old->url())], true, $failure === 'upload');
    fakeCommandPrompts($failure === 'none' ? [] : [false]);
    expect(syncCommand(['--container' => 'assets'])->handle())->toBe(0);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/folders'));
})->with(['none', 'broken', 'upload']);

it('selects among configured connections', function () {
    config(['statamic.fairu.connections.second' => ['tenant' => 'second']]);
    fakeCommandPrompts(['second', 'assets']);
    fakeCommandApi();
    mockCommandContainer([]);
    expect(syncCommand()->handle())->toBe(0);
    Http::assertSent(fn ($r) => $r->hasHeader('Tenant', 'second'));
});

it('replaces asset field types only in YAML files', function () {
    fakeCommandPrompts();
    $dir = sys_get_temp_dir().'/fairu-blueprints-'.bin2hex(random_bytes(5));
    File::makeDirectory($dir);
    File::put($dir.'/asset.yaml', "fields:\n  type: assets\n");
    File::put($dir.'/plain.yaml', 'type: text');
    File::put($dir.'/ignore.txt', 'type: assets');
    \Statamic\Facades\Blueprint::shouldReceive('directory')->andReturn($dir);
    \Statamic\Facades\Fieldset::shouldReceive('directory')->andReturn($dir.'/missing');
    try {
        (new Setup())->replaceFields();
        expect(File::get($dir.'/asset.yaml'))->toContain('type: fairu')
            ->and(File::get($dir.'/plain.yaml'))->toBe('type: text')
            ->and(File::get($dir.'/ignore.txt'))->toBe('type: assets');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('reports a setup with no configured containers', function () {
    fakeCommandPrompts(['default', true]);
    fakeCommandApi();
    \Statamic\Facades\AssetContainer::shouldReceive('all')->andReturn(collect());
    expect(fn () => (new Setup())->handle())->toThrow(Error::class, 'Error while loading statamic containers');
});
