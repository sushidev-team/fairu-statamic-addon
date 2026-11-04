<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Antlers;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Sushidev\Fairu\Fieldtypes\Fairu as FairuFieldtype;
use Sushidev\Fairu\Services\FairuAssetRenderer;
use Sushidev\Fairu\Support\WatermarkSignature;
use Sushidev\Fairu\Tags\FairuAssetTags;

const WM_FILE_ID = '550e8400-e29b-41d4-a716-446655440000';
const WM_KEY = 'fwm_d9xh6ssIShqKkolIJk7TaARqO4vz3YEpaTZaoNAinTE';

beforeEach(function () {
    config()->set('statamic.fairu.connections.default.watermark_key', WM_KEY);
});

function wmTag(array $params, string $method = 'url'): mixed
{
    $tag = new FairuAssetTags;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->{$method}();
}

/**
 * @return array<string, string>
 */
function wmQuery(string $url): array
{
    return WatermarkSignature::parseQuery((string) parse_url(html_entity_decode($url), PHP_URL_QUERY));
}

function expectValidWatermarkSignature(string $url, string $key = WM_KEY): void
{
    $query = wmQuery($url);

    expect($query)->toHaveKey('wm', '0')
        ->and($query)->toHaveKey('wms')
        ->and($query['wms'])->toBe(WatermarkSignature::sign($key, WM_FILE_ID, $query));
}

it('does not sign by default', function () {
    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600]);

    expect(wmQuery($url))->not->toHaveKey('wm')->not->toHaveKey('wms');
});

it('signs the final query when watermark="false"', function (mixed $flag) {
    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600, 'watermark' => $flag]);

    expect($url)->toContain('width=1600')->toContain('quality=90')->toEndWith('wms='.wmQuery($url)['wms']);
    expectValidWatermarkSignature($url);
})->with(['bool false' => false, 'string false' => 'false', 'zero' => '0']);

it('keeps the watermark for anything but an explicit false', function (mixed $flag) {
    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600, 'watermark' => $flag]);

    expect(wmQuery($url))->not->toHaveKey('wms');
})->with(['true' => true, 'string true' => 'true', 'garbage' => 'maybe', 'empty' => '']);

it('produces a signature that breaks when any parameter changes', function () {
    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600, 'watermark' => false]);
    $query = wmQuery($url);
    $query['width'] = '3200';

    expect(WatermarkSignature::sign(WM_KEY, WM_FILE_ID, $query))->not->toBe($query['wms']);
});

it('signs the canonical lower-case file ID', function () {
    $url = wmTag(['id' => strtoupper(WM_FILE_ID), 'name' => 'hero.jpg', 'width' => 1600, 'watermark' => false]);

    expectValidWatermarkSignature($url);
});

it('pins the query separator only for signed URLs', function () {
    $separator = ini_set('arg_separator.output', ';');

    try {
        $signed = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600, 'watermark' => false]);
        $unsigned = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 1600]);
    } finally {
        ini_set('arg_separator.output', $separator);
    }

    expect($signed)->not->toContain(';');
    expectValidWatermarkSignature($signed);
    expect($unsigned)->toContain('width=1600;quality=90');
});

it('signs only wm=0 for raw originals', function () {
    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'plan.jpg', 'raw' => 'true', 'watermark' => 'false']);
    $query = wmQuery($url);

    expect(array_keys($query))->toBe(['wm', 'wms'])
        ->and($url)->toBe('https://files.fairu.app/'.WM_FILE_ID.'/plan.jpg?wm=0&wms='.$query['wms'])
        ->and($query['wms'])->toBe('j7lsodayymlxtSYwnPWD96');
});

it('signs every srcset entry with its own signature', function (string $method) {
    $out = wmTag([
        'id' => WM_FILE_ID,
        'ids' => [WM_FILE_ID],
        'name' => 'hero.jpg',
        'width' => 1600,
        'sources' => '320,320w;640,640w;1280,1280w',
        'watermark' => false,
    ], $method);

    $html = $method === 'index' ? $out->first()['srcset'] : (string) $out;

    preg_match_all('#https://files\.fairu\.app/[^ \']+#', $html, $matches);
    $urls = $matches[0];

    expect(count($urls))->toBeGreaterThanOrEqual(3);

    $signatures = [];
    foreach ($urls as $url) {
        expectValidWatermarkSignature($url);
        $signatures[] = wmQuery($url)['wms'];
    }

    expect(array_unique($signatures))->toHaveCount(count($signatures));
})->with(['index', 'image', 'images']);

it('emits an unsigned URL and logs once when no key is configured', function () {
    config()->set('statamic.fairu.connections.default.watermark_key', null);
    Log::spy();

    $first = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 800, 'watermark' => false]);
    $second = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 400, 'watermark' => false]);

    expect(wmQuery($first))->not->toHaveKey('wm')->not->toHaveKey('wms')
        ->and(wmQuery($second))->not->toHaveKey('wms');

    Log::shouldHaveReceived('warning')->once();
});

it('never throws on an invalid key', function () {
    config()->set('statamic.fairu.connections.default.watermark_key', 'not-a-key');
    Log::spy();

    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 800, 'watermark' => false]);

    expect(wmQuery($url))->not->toHaveKey('wms');
    Log::shouldHaveReceived('warning')->once();
});

it('uses the key of the selected connection', function () {
    $otherKey = 'fwm_GPDz3Gy4XFsiLVcMxBNlz0WfVIaoaR9INQIDRYP5TFE';
    config()->set('statamic.fairu.connections.second', ['watermark_key' => $otherKey]);

    $url = wmTag(['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'width' => 800, 'watermark' => false, 'connection' => 'second']);

    expectValidWatermarkSignature($url, $otherKey);
});

it('signs in deferred rendering', function (string $type) {
    $renderer = new FairuAssetRenderer;
    $asset = ['id' => WM_FILE_ID, 'name' => 'hero.jpg', 'is_image' => true];
    $params = ['id' => WM_FILE_ID, 'watermark' => false, 'sources' => '320,320w;640,640w'];

    $out = $type === 'list'
        ? $renderer->renderList([$asset], $params, '{{ url }} {{ srcset }}', [])
        : $renderer->render($type, $params, $asset);

    preg_match_all('#https://files\.fairu\.app/[^ \']+#', $out, $matches);

    expect($matches[0])->not->toBeEmpty();
    foreach ($matches[0] as $url) {
        expectValidWatermarkSignature(rtrim($url, ','));
    }
})->with(['url', 'image', 'list']);

function wmFieldValue(bool $disable): Value
{
    $fieldtype = (new FairuFieldtype)->setField(new Field('image', ['type' => 'fairu', 'disable_watermark' => $disable]));

    return new Value(WM_FILE_ID, 'image', $fieldtype);
}

it('applies the fieldtype default to the tag', function () {
    $url = wmTag(['id' => wmFieldValue(true), 'name' => 'hero.jpg', 'width' => 800]);

    expectValidWatermarkSignature($url);
});

it('resolves the fieldtype default from a colon parameter', function () {
    $tag = new FairuAssetTags;
    $tag->setContext(['image' => wmFieldValue(true)]);
    $tag->setParameters([':id' => 'image', 'name' => 'hero.jpg', 'width' => 800]);

    expectValidWatermarkSignature($tag->url());
});

it('lets the tag parameter override the fieldtype default', function () {
    $url = wmTag(['id' => wmFieldValue(true), 'name' => 'hero.jpg', 'width' => 800, 'watermark' => 'true']);

    expect(wmQuery($url))->not->toHaveKey('wms');
});

it('does not sign when the field leaves the watermark on', function () {
    $url = wmTag(['id' => wmFieldValue(false), 'name' => 'hero.jpg', 'width' => 800]);

    expect(wmQuery($url))->not->toHaveKey('wms');
});

it('carries the fieldtype default into deferred params', function () {
    $tag = new FairuAssetTags;
    $tag->setContext([]);
    $tag->setParameters(['id' => wmFieldValue(true)]);

    expect($tag->params->get('watermark'))->toBeFalse();
});

it('offers the disable watermark option on the fieldtype', function () {
    expect((new FairuFieldtype)->configFields()->all()->keys())->toContain('disable_watermark');
});

it('renders the fieldtype default through Antlers', function () {
    $out = (string) Antlers::parse(
        '{{ fairu:url :id="image" name="hero.jpg" width="800" }}',
        ['image' => wmFieldValue(true)],
        true,
    );

    expectValidWatermarkSignature($out);
});

it('signs the augmented field URL when the field disables the watermark', function (bool $disable) {
    Http::fake(['*' => Http::response([], 500)]);
    Log::spy();

    $fieldtype = (new FairuFieldtype)->setField(new Field('image', ['type' => 'fairu', 'max_files' => 1, 'disable_watermark' => $disable]));
    $url = data_get($fieldtype->shallowAugment(WM_FILE_ID), 'url');

    if ($disable) {
        expect($url)->toStartWith('https://files.fairu.app/'.WM_FILE_ID.'/file?wm=0&wms=');
        expectValidWatermarkSignature($url);
    } else {
        expect($url)->toBe('https://files.fairu.app/'.WM_FILE_ID.'/file');
    }
})->with(['disabled' => true, 'enabled' => false]);

it('leaves the augmented field URL without a query when no usable key exists', function (?string $key) {
    Http::fake(['*' => Http::response([], 500)]);
    Log::spy();
    config()->set('statamic.fairu.connections.default.watermark_key', $key);

    $fieldtype = (new FairuFieldtype)->setField(new Field('image', ['type' => 'fairu', 'max_files' => 1, 'disable_watermark' => true]));

    expect(data_get($fieldtype->shallowAugment(WM_FILE_ID), 'url'))->toBe('https://files.fairu.app/'.WM_FILE_ID.'/file');
})->with(['missing' => null, 'invalid' => 'not-a-key']);
