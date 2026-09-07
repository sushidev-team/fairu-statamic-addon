<?php

use Illuminate\Support\Facades\Http;
use Sushidev\Fairu\Commands\Concerns\UploadsAssetsToFairu;

class ImportHarness
{
    use UploadsAssetsToFairu;
    protected string $connection = 'default';
    public function upload($asset): bool
    {
        return $this->importAssetToFairu($asset, TEMPLATE_ID, [], new class { public function label($label) {} });
    }
    public function retry(array $failed): array
    {
        $list = [];
        $this->retryFailedUploads([], $list, $failed);
        return [$list, $failed];
    }
}

it('reports import upload failures at each stage', function ($failure) {
    fakeCommandPrompts();
    Http::fake([
        '*/api/files' => Http::response($failure === 'create' ? [] : ['upload_url' => 'https://upload.example/file', 'sync_url' => 'https://upload.example/sync']),
        'https://upload.example/file' => fn () => $failure === 'transport' ? throw new RuntimeException('offline') : Http::response('', $failure === 'upload' ? 500 : 200),
        'https://upload.example/sync' => Http::response('', $failure === 'sync' ? 500 : 200),
    ]);
    expect((new ImportHarness())->upload(commandAsset('image.webp')))->toBe($failure === 'none');
})->with(['create', 'transport', 'upload', 'sync', 'none']);

it('retries failed imports and retains irrecoverable entries', function ($scenario) {
    fakeCommandPrompts($scenario === 'success' ? [true] : [true, false]);
    fakeCommandApi();
    $entry = ['fairu' => TEMPLATE_ID, 'path' => 'image.webp', 'url' => '/image.webp'];
    if ($scenario !== 'missing') {
        $entry['asset'] = commandAsset('image.webp', $scenario === 'broken');
    }
    [$list, $failed] = (new ImportHarness())->retry([$entry]);
    expect($list)->toHaveCount($scenario === 'success' ? 1 : 0)
        ->and($failed)->toHaveCount($scenario === 'success' ? 0 : 1);
})->with(['success', 'missing', 'broken']);

it('handles optional, plain and rich text migration fields', function ($value, $expected) {
    $asset = Mockery::mock(\Statamic\Contracts\Assets\Asset::class);
    $asset->shouldReceive('data')->andReturn(collect(['caption' => $value]));
    $asset->shouldReceive('path')->andReturn('image.webp');
    $harness = new ImportHarness();
    expect(invokeFairu($harness, 'augmentBardField', $asset, null))->toBeNull();
    $out = invokeFairu($harness, 'augmentBardField', $asset, 'caption');
    expect($out)->toBe($expected);
})->with([
    [null, null], ['', null], [false, null], [42, ''], ['plain text', 'plain text'],
    [[['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]]], '<p>hello</p>'],
    [['invalid'], ''],
]);

it('tolerates failures while reading migration metadata', function () {
    $asset = Mockery::mock(\Statamic\Contracts\Assets\Asset::class);
    $asset->shouldReceive('data')->andThrow(new RuntimeException('corrupt metadata'));
    $asset->shouldReceive('path')->andReturn('image.webp');
    expect(invokeFairu(new ImportHarness(), 'augmentBardField', $asset, 'caption'))->toBeNull();
});
