<?php

use Sushidev\Fairu\Tags\FairuAssetTags;

/**
 * A UUID id skips the old-asset-path resolution (which would hit asset
 * containers), so these tests exercise pure URL building with no network.
 */
const FAIRU_TEST_ID = '11111111-1111-1111-1111-111111111111';

function fairuUrlTag(array $params): string
{
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters($params);

    return (string) $tag->url();
}

it('appends the transform query by default', function () {
    $out = fairuUrlTag(['id' => FAIRU_TEST_ID, 'name' => 'hero.webp']);

    expect($out)->toContain(FAIRU_TEST_ID.'/hero.webp')
        ->and($out)->toContain('quality=90');
});

it('suppresses the transform query when raw="true"', function () {
    $out = fairuUrlTag(['id' => FAIRU_TEST_ID, 'name' => 'plan.pdf', 'raw' => 'true']);

    expect($out)->toContain(FAIRU_TEST_ID.'/plan.pdf')
        ->and($out)->not->toContain('?')
        ->and($out)->not->toContain('quality=90');
});

it('emits a same-origin download route when download="true"', function () {
    $out = fairuUrlTag(['id' => FAIRU_TEST_ID, 'name' => 'plan.pdf', 'download' => 'true']);

    expect($out)->toContain('/fairu/download/'.FAIRU_TEST_ID.'/plan.pdf')
        ->and($out)->not->toContain('files.fairu.app');
});

it('forwards video versions across all tags and source URLs', function (string $method) {
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters([
        'id' => FAIRU_TEST_ID,
        'ids' => [FAIRU_TEST_ID],
        'name' => 'video.mp4',
        'version' => 'medium',
    ]);

    $out = $tag->{$method}();
    $url = $method === 'index' ? $out->first()['url'] : (string) $out;

    expect($url)->toContain('version=medium');
})->with(['url', 'index', 'image', 'images']);

it('keeps original-file options authoritative over version', function (string $option) {
    $out = fairuUrlTag([
        'id' => FAIRU_TEST_ID,
        'name' => 'video.mp4',
        'version' => 'medium',
        $option => 'true',
    ]);

    expect($out)->not->toContain('?');
})->with(['raw', 'download']);

it('forwards versions in deferred rendering and generated sources', function (string $type) {
    $renderer = new \Sushidev\Fairu\Services\FairuAssetRenderer();
    $asset = ['id' => FAIRU_TEST_ID, 'name' => 'video.mp4', 'is_image' => false];
    $params = ['id' => FAIRU_TEST_ID, 'version' => 'medium'];
    $out = $type === 'list'
        ? $renderer->renderList([$asset], $params, '{{ url }}', [])
        : $renderer->render($type, $params, $asset);

    expect($out)->toContain('version=medium');

    $params['sources'] = '320,320w;640,640w';
    $sources = $renderer->renderList([$asset], $params, '{{ srcset }}', []);

    expect(substr_count($sources, 'version=medium'))->toBe(2);
})->with(['url', 'image', 'list']);
