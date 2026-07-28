<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Statamic\View\Antlers\Language\Runtime\GlobalRuntimeState;
use Sushidev\Fairu\Http\Middleware\CoalesceFairuMeta;
use Sushidev\Fairu\Services\FairuAssetRenderer;
use Sushidev\Fairu\Services\FairuMetaBag;

afterEach(function () {
    GlobalRuntimeState::$isCacheEnabled = false;
});

function runCoalesceMiddleware(string $html): string
{
    $response = (new CoalesceFairuMeta())->handle(
        Request::create('/'),
        fn () => new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8'])
    );

    return (string) $response->getContent();
}

it('defers while the bag is active', function () {
    $bag = new FairuMetaBag();
    $bag->enable();

    expect($bag->shouldDefer())->toBeTrue();
});

it('does not defer while the bag is inactive', function () {
    expect((new FairuMetaBag())->shouldDefer())->toBeFalse();
});

it('does not defer inside an Antlers cache tag', function () {
    $bag = new FairuMetaBag();
    $bag->enable();

    GlobalRuntimeState::$isCacheEnabled = true;

    // A token emitted here would be baked into the cached fragment and replayed
    // on later requests, where no bag can resolve it.
    expect($bag->shouldDefer())->toBeFalse();
});

it('strips placeholders replayed from a cached fragment', function () {
    $token = (new FairuMetaBag())->token('af1c6c523943d711');

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, $token));

    $out = runCoalesceMiddleware('<a href="">'.$token.'</a>');

    expect($out)->toBe('<a href=""></a>')
        ->and($out)->not->toContain('__FAIRU_');
});

it('leaves markup without placeholders untouched', function () {
    $html = '<a href="/x"><img src="https://files.example/1/a.webp"></a>';

    expect(runCoalesceMiddleware($html))->toBe($html);
});

it('renders a deferred tag body that contains a partial', function () {
    // Regression: Antlers::parse() defaults to $trusted = false, which sandboxes the parse
    // as user-authored content. NodeProcessor::guardRuntimeTag() then rejects {{ partial }}
    // and the whole body renders as an empty string, so a deferred {{ fairu }} block wrapping
    // its <img> in a partial silently disappeared.
    View::addNamespace('test-partials', __DIR__.'/fixtures');

    $out = (new FairuAssetRenderer())->renderList(
        assets: [['id' => 'af1c6c52-3943-4d71-8f0e-000000000001', 'name' => 'logo.svg', 'is_image' => true]],
        params: [],
        body: '{{ partial src="test-partials::wrapper" }}<img src="{{ url }}">{{ /partial }}',
        context: [],
    );

    expect($out)->toContain('<img src=')
        ->and($out)->toContain('<div class="wrapper">');
});
