<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Antlers;
use Statamic\Facades\Statamic;
use Sushidev\Fairu\Http\Middleware\CoalesceFairuMeta;
use Sushidev\Fairu\Services\FairuMetaBag;

it('renders equivalent images through Antlers, native Blade tags and Blade components', function ($coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $antlers = renderFairuTemplate('{{ fairu:image :id="id" width="800" fetchMeta="true" }}', 'antlers', $coalesce);
    $native = renderFairuTemplate('<s:fairu:image :id="$id" width="800" fetchMeta="true" />', 'blade', $coalesce);
    $component = renderFairuTemplate('<x-fairu::image :id="$id" width="800" fetch-meta="true" />', 'blade', $coalesce);

    expect($native)->toBe($antlers)->and($component)->toBe($antlers)
        ->and($antlers)->toContain('hero.webp', 'width="800"', 'alt="A &quot;hero&quot;"', 'focal=40-30-1')
        ->not->toContain('__FAIRU_');
})->with([false, true]);

it('renders tag pairs with the correct language and outer context', function ($language, $coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $template = $language === 'blade'
        ? '<s:fairu :ids="$ids" fetchMeta="true"><figure>{{ $title }}:{{ $name }}:{{ $focus_css }}</figure></s:fairu>'
        : '{{ fairu :ids="ids" fetchMeta="true" }}<figure>{{ title }}:{{ name }}:{{ focus_css }}</figure>{{ /fairu }}';
    $out = renderFairuTemplate($template, $language, $coalesce, ['title' => 'Gallery']);
    expect($out)->toContain('<figure>Gallery:hero.webp:40% 30%</figure>', '<figure>Gallery:second.webp:50% 50%</figure>')
        ->not->toContain('__FAIRU_', '$title');
    Http::assertSentCount(1);
})->with(['antlers', 'blade'])->with([false, true]);

it('renders multiple images and batches mixed tag kinds', function ($language, $coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $template = $language === 'blade'
        ? '<x-fairu::images :ids="$ids" fetch-meta="true" /><s:fairu:image :id="$id" fetchMeta="true" />'
        : '{{ fairu:images :ids="ids" fetchMeta="true" }}{{ fairu:image :id="id" fetchMeta="true" }}';
    $out = renderFairuTemplate($template, $language, $coalesce);
    expect(substr_count($out, '<img '))->toBe(3);
    expect($out)->toContain('hero.webp', 'second.webp')->not->toContain('__FAIRU_');
    Http::assertSentCount($coalesce ? 1 : 2);
})->with(['antlers', 'blade'])->with([false, true]);

it('keeps local images local even when batching is enabled', function () {
    $out = renderFairuTemplate('<x-fairu::image :id="$id" name="local.webp" /><x-fairu::url :id="$id" name="plan.pdf" raw="true" />', 'blade', true);
    expect($out)->toContain('local.webp', 'plan.pdf')->not->toContain('__FAIRU_');
    Http::assertNothingSent();
});

it('supports Blade attribute bags and does not evaluate attribute values as template source', function ($coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $out = renderFairuTemplate('<x-fairu::image :id="$id" :params="$params" {{ $attributes->merge([\'class\' => \'base\']) }} />', 'blade', $coalesce, [
        'params' => ['fetchMeta' => true, 'alt' => '" onload="bad {{ 7 * 7 }}', 'sources' => '320,320w;640,640w', 'ratio' => '16/9'],
        'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'custom', 'data-label' => 'A & B', 'loading' => 'lazy']),
    ]);
    expect($out)->toContain('class="base custom"', 'loading="lazy"', 'data-label="A &amp; B"', '{{ 7 * 7 }}', 'alt="&quot; onload=&quot;bad', 'height=180')
        ->not->toContain('alt="" onload=', '__FAIRU_');
})->with([false, true]);

it('supports URL components with safe HTML output', function ($coalesce) {
    Http::fake(['*/api/files/meta' => Http::response([['id' => TEMPLATE_ID, 'name' => 'a"b.webp']])]);
    $out = renderFairuTemplate('<a href="<x-fairu::url :id="$id" fetch-meta="true" width="800" />">Image</a>', 'blade', $coalesce);
    expect($out)->toContain('a&quot;b.webp', '&amp;quality=90')->not->toContain('__FAIRU_');
})->with([false, true]);

it('supports full metadata without replacing it with lean metadata', function ($tag, $coalesce) {
    Http::fake(['*/api/files/list' => Http::response(templateMeta())]);
    $param = $tag === 'images' ? ':ids="$ids"' : ':id="$id"';
    $out = renderFairuTemplate('<x-fairu::'.$tag.' '.$param.' fetch-meta="full" />', 'blade', $coalesce);
    expect($out)->toContain('hero.webp')->not->toContain('__FAIRU_');
    Http::assertSentCount(1);
})->with(['image', 'images', 'url'])->with([false, true]);

it('supports fluent tags for URLs and collections', function () {
    $url = \Statamic\Statamic::tag('fairu:url')->params(['id' => TEMPLATE_ID, 'raw' => true, 'name' => 'plan.pdf'])->fetch();
    $files = \Statamic\Statamic::tag('fairu')->params(['ids' => [TEMPLATE_ID], 'sources' => '320,320w'])->fetch();
    expect($url)->toBe('https://files.fairu.app/'.TEMPLATE_ID.'/plan.pdf')
        ->and($files)->toHaveCount(1)->and($files[0]['srcset'])->toContain('320w');
});

it('renders empty inputs without requests', function () {
    expect(renderFairuTemplate('<x-fairu::image /><x-fairu::images /><x-fairu::url />', 'blade'))->toBe('');
    expect(renderFairuTemplate('<s:fairu :ids="$ids">unreachable</s:fairu>', 'blade', true, ['ids' => []]))->toBe('');
    Http::assertNothingSent();
});

it('preserves scoped Blade loop data during deferred rendering', function ($coalesce) {
    Http::fake(['*/api/files/meta' => Http::response(templateMeta())]);
    $out = renderFairuTemplate('<s:fairu :ids="$ids" fetchMeta="true" scope="file">{{ $loop->iteration }}/{{ $loop->count }}:{{ $file[\'name\'] }};</s:fairu>', 'blade', $coalesce);
    expect($out)->toBe('1/2:hero.webp;2/2:second.webp;');
})->with([false, true]);

it('escapes bound Blade attributes exactly once', function () {
    $out = renderFairuTemplate('<x-fairu::image :id="$id" :alt="$alt" :data-title="$alt" :width="800" />', 'blade', false, ['alt' => 'Rock & "roll"']);
    expect($out)->toContain('alt="Rock &amp; &quot;roll&quot;"', 'data-title="Rock &amp; &quot;roll&quot;"')
        ->not->toContain('&amp;amp;', '&amp;quot;');
});
