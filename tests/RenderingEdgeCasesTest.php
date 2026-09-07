<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sushidev\Fairu\Services\FairuAssetRenderer;
use Sushidev\Fairu\Services\FairuMetaBag;
use Sushidev\Fairu\Http\Middleware\CoalesceFairuMeta;
use Sushidev\Fairu\Tags\FairuAssetTags;
use Sushidev\Fairu\Traits\TransformAssets;

it('isolates deferred metadata by connection and falls back for missing files', function () {
    config(['statamic.fairu.connections.second' => ['tenant' => 'second']]);
    Http::fake(fn ($request) => Http::response([['id' => TEMPLATE_ID, 'name' => $request->header('Tenant')[0].'.webp']]));
    $out = renderFairuTemplate('<x-fairu::image :id="$id" fetch-meta="true" /><x-fairu::image :id="$id" fetch-meta="true" connection="second" /><x-fairu::images :ids="$ids" fetch-meta="true" />', 'blade', true);
    expect($out)->toContain('second.webp', TEMPLATE_ID_2.'/file')->not->toContain('__FAIRU_');
    Http::assertSentCount(2);
});

it('isolates synchronous metadata caches by connection', function () {
    config(['statamic.fairu.connections.second' => ['tenant' => 'second']]);
    Http::fake(fn ($request) => Http::response([['id' => TEMPLATE_ID, 'name' => ($request->header('Tenant')[0] ?: 'first').'.webp']]));
    $out = renderFairuTemplate('<x-fairu::image :id="$id" fetch-meta="true" /><x-fairu::image :id="$id" fetch-meta="true" connection="second" />', 'blade');
    expect($out)->toContain('first.webp', 'second.webp');
    Http::assertSentCount(2);
});

it('renders nested tags inside deferred bodies without leaking tokens', function ($language) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $template = $language === 'blade'
        ? '<s:fairu :id="$id" fetchMeta="true"><x-fairu::image :id="$id" fetch-meta="true" /></s:fairu>'
        : '{{ fairu :id="id" fetchMeta="true" }}{{ fairu:image :id="id" fetchMeta="true" }}{{ /fairu }}';
    expect(renderFairuTemplate($template, $language, true))->toContain('hero.webp')->not->toContain('__FAIRU_');
})->with(['antlers', 'blade']);

it('falls back to local URLs when metadata fails', function ($coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(['error' => 'unavailable'], 503)]);
    $out = renderFairuTemplate('<x-fairu::image :id="$id" fetch-meta="true" />', 'blade', $coalesce);
    expect($out)->toContain(TEMPLATE_ID.'/file')->not->toContain('__FAIRU_');
})->with([false, true]);

it('cleans the request bag on all response paths', function ($kind) {
    $response = match ($kind) {
        'stream' => new \Symfony\Component\HttpFoundation\StreamedResponse(fn () => null),
        'binary' => new \Symfony\Component\HttpFoundation\BinaryFileResponse(__FILE__),
        'json' => new Response('{}', 200, ['Content-Type' => 'application/json']),
        'empty' => new Response(''),
    };
    $result = (new CoalesceFairuMeta())->handle(Request::create('/'), fn () => $response);
    expect($result)->toBe($response)->and(app(FairuMetaBag::class)->isActive())->toBeFalse();
})->with(['stream', 'binary', 'json', 'empty']);

it('cleans the bag when rendering throws', function () {
    expect(fn () => (new CoalesceFairuMeta())->handle(Request::create('/'), fn () => throw new RuntimeException('render failed')))->toThrow(RuntimeException::class);
    expect(app(FairuMetaBag::class)->isActive())->toBeFalse();
});

it('allows disabling coalescing', function () {
    config(['statamic.fairu.coalesce_meta' => false]);
    expect((new CoalesceFairuMeta())->handle(Request::create('/'), fn () => new Response('test'))->getContent())->toBe('test');
});

it('ignores discarded placeholders and metadata records without ids', function () {
    Http::fake(['*/api/files/meta' => Http::response([['name' => 'invalid']])]);
    $out = (new CoalesceFairuMeta())->handle(Request::create('/'), function () {
        $bag = app(FairuMetaBag::class);
        $bag->queue('image', TEMPLATE_ID, []);
        return new Response($bag->queue('url', null, []));
    })->getContent();
    expect($out)->toBe('');
});

it('handles empty renderer input and focal point fallbacks', function () {
    $renderer = new FairuAssetRenderer();
    expect($renderer->render('unknown', [], null))->toBe('')
        ->and($renderer->renderList([], [], '', []))->toBe('')
        ->and($renderer->formatFocalPoint('invalid'))->toBe('50% 50%')
        ->and(invokeFairu($renderer, 'getConnectionName'))->toBe('default');
    $bag = new FairuMetaBag();
    expect($bag->meta(null))->toBeNull();
});

it('builds explicit download URLs and responsive sources safely', function () {
    $renderer = new FairuAssetRenderer();
    $url = $renderer->render('url', ['download' => true, 'name' => 'plan.pdf'], ['id' => TEMPLATE_ID]);
    expect($url)->toContain('/fairu/download/'.TEMPLATE_ID.'/plan.pdf');
    $out = $renderer->render('image', ['sources' => 'invalid;320,180,320w', 'raw' => false], ['id' => TEMPLATE_ID]);
    expect($out)->toContain('height=180', '320w')->not->toContain('invalid');
    expect($renderer->render('image', ['sources' => '320,320w', 'raw' => true], ['id' => TEMPLATE_ID]))->not->toContain('srcset');
});

it('supports transformation helpers in fieldtype and request contexts', function () {
    $plain = new class { use TransformAssets; };
    $field = new class { use TransformAssets; public function config($key, $default) { return 'second'; } };
    $controller = new class { use TransformAssets; public function request() { return Request::create('/?quality=70'); } };
    expect(invokeFairu($plain, 'getConnectionName'))->toBe('default')
        ->and(invokeFairu($field, 'getConnectionName'))->toBe('second')
        ->and(invokeFairu($plain, 'fetchMetaParam'))->toBeFalse()
        ->and(invokeFairu($plain, 'getParam', 'quality', 40))->toBe(40)
        ->and(invokeFairu($plain, 'getParam', 'quality', null, 90))->toBe(90)
        ->and(invokeFairu($controller, 'getParam', 'quality'))->toBe('70')
        ->and(invokeFairu($plain, 'downloadUrl', null))->toBeNull()
        ->and(invokeFairu($plain, 'buildFileUrl', null))->toBeNull()
        ->and(invokeFairu($plain, 'getFile', null))->toBeNull();
    config(['statamic.fairu.url_proxy' => 'https://files.example/']);
    expect(invokeFairu($plain, 'buildFileUrl', TEMPLATE_ID))->toBe('https://files.example/'.TEMPLATE_ID.'/file');
});

it('supports legacy asset ids and transformation parameters in tags', function () {
    \Illuminate\Support\Facades\Storage::fake('legacy');
    $container = \Statamic\Facades\AssetContainer::make('legacy')->disk('legacy');
    \Statamic\Facades\AssetContainer::shouldReceive('all')->andReturn(collect([$container]));
    \Statamic\Facades\AssetContainer::shouldReceive('findByHandle')->with('legacy')->andReturn($container);
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters(['id' => 'legacy.webp', 'quality' => 75]);
    $id = (new \Sushidev\Fairu\Services\Fairu())->convertToUuid(\Illuminate\Support\Facades\Storage::disk('legacy')->url('legacy.webp'));
    expect($tag->url())->toContain($id)
        ->and(invokeFairu($tag, 'getUrl', TEMPLATE_ID, 'image.webp', null, null, '50-50-1', null, true))->toContain('quality=75');
});
