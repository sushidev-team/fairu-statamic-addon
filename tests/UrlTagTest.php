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
