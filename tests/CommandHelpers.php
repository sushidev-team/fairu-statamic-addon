<?php

use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\ConfirmPrompt;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Sushidev\Fairu\Services\Fairu;

function fakeCommandPrompts(array $answers = []): void
{
    Prompt::fake();
    Prompt::fallbackWhen(true);
    $answer = function ($prompt) use (&$answers) {
        expect($answers)->not->toBeEmpty('Unexpected prompt: '.$prompt->label);
        return array_shift($answers);
    };
    SelectPrompt::fallbackUsing($answer);
    ConfirmPrompt::fallbackUsing($answer);
    \Laravel\Prompts\Note::fallbackUsing(fn () => true);
    \Laravel\Prompts\Table::fallbackUsing(fn () => true);
}

function commandAsset(string $path, bool $broken = false): object
{
    $asset = Mockery::mock(\Statamic\Contracts\Assets\Asset::class);
    $asset->shouldReceive('id')->andReturn('assets::'.$path);
    $asset->shouldReceive('url')->andReturn('/assets/'.$path);
    if ($broken) {
        $asset->shouldReceive('path')->andThrow(new RuntimeException('unreadable asset'));
    } else {
        $asset->shouldReceive('path')->andReturn($path);
    }
    $asset->shouldReceive('basename')->andReturn(basename($path));
    $asset->shouldReceive('contents')->andReturn('image bytes');
    $asset->shouldReceive('mimeType')->andReturn('image/webp');
    $asset->shouldReceive('data')->andReturn(collect(['alt' => 'Alt', 'caption' => 'Caption', 'description' => null]));
    return $asset;
}

function mockCommandContainer(array $assets): void
{
    $container = Mockery::mock(\Statamic\Contracts\Assets\AssetContainer::class);
    $container->shouldReceive('handle')->andReturn('assets');
    $container->shouldReceive('folders')->andReturn(collect(['photos']));
    AssetContainer::shouldReceive('all')->andReturn(collect([['handle' => 'assets']]));
    AssetContainer::shouldReceive('find')->with('assets')->andReturn($container);
    Asset::shouldReceive('whereContainer')->with('assets')->andReturn(collect($assets));
}

function fakeCommandApi(array $existing = [], bool $folderError = false, bool $uploadError = false): void
{
    Http::fake([
        '*/api/users/scope' => Http::response(['id' => 'user', 'email' => 'test@example.test']),
        '*/api/files/list' => Http::response(array_map(fn ($id) => ['id' => $id], $existing)),
        '*/api/folders' => Http::response([], $folderError ? 500 : 200),
        '*/api/files' => Http::response(['upload_url' => 'https://upload.example/file', 'sync_url' => 'https://upload.example/sync']),
        'https://upload.example/file' => Http::response('', $uploadError ? 500 : 200),
        'https://upload.example/sync' => Http::response([]),
    ]);
}
