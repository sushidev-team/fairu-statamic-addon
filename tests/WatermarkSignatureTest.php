<?php

use Sushidev\Fairu\Support\WatermarkSignature;

function watermarkVectors(): array
{
    return json_decode(file_get_contents(__DIR__.'/fixtures/watermark-signature-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
}

function watermarkVectorKey(int $version): array
{
    return collect(watermarkVectors()['keys'])->firstWhere('key_version', $version);
}

it('decodes display keys to the raw key bytes', function (array $key) {
    expect(bin2hex(WatermarkSignature::rawKey($key['display_key'])))->toBe($key['raw_key_hex']);
})->with(fn () => collect(watermarkVectors()['keys'])->mapWithKeys(fn ($k) => ['v'.$k['key_version'] => [$k]])->all());

it('matches the shared signature vectors', function (array $case) {
    $vectors = watermarkVectors();
    $key = watermarkVectorKey($case['key_version']);

    expect(WatermarkSignature::canonical($vectors['file_id'], $case['query']))->toBe($case['canonical'])
        ->and(WatermarkSignature::sign($key['display_key'], $vectors['file_id'], $case['query']))->toBe($case['wms']);
})->with(fn () => collect(watermarkVectors()['cases'])->mapWithKeys(fn ($c) => [$c['name'] => [$c]])->all());

it('ignores an existing wms parameter when signing', function () {
    $vectors = watermarkVectors();
    $case = $vectors['cases'][0];
    $key = watermarkVectorKey(1)['display_key'];

    expect(WatermarkSignature::sign($key, $vectors['file_id'], $case['query'] + ['wms' => 'tampered']))->toBe($case['wms']);
});

it('rejects malformed display keys', function (string $key) {
    WatermarkSignature::rawKey($key);
})->throws(InvalidArgumentException::class)->with([
    'missing prefix' => 'd9xh6ssIShqKkolIJk7TaARqO4vz3YEpaTZaoNAinTE',
    'empty' => 'fwm_',
    'not base64url' => 'fwm_d9xh6ssIShqKkolIJk7TaARq+4vz3YEpaTZaoNAinTE',
    'wrong length' => 'fwm_d9xh6ssIShqKkolIJk7TaA',
]);

it('parses query strings like the proxy does', function () {
    expect(WatermarkSignature::parseQuery('width=800&tint=%2300ff00&name=a+b&empty=&wm=0'))->toBe([
        'width' => '800',
        'tint' => '#00ff00',
        'name' => 'a b',
        'empty' => '',
        'wm' => '0',
    ]);
});
