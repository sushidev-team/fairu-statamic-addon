<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Antlers;
use Statamic\Facades\Statamic;
use Sushidev\Fairu\Http\Middleware\CoalesceFairuMeta;
use Sushidev\Fairu\Services\FairuMetaBag;

const TEMPLATE_ID = '11111111-1111-4111-8111-111111111111';
const TEMPLATE_ID_2 = '22222222-2222-4222-8222-222222222222';

function templateMeta(): array
{
    return [
        ['id' => TEMPLATE_ID, 'name' => 'hero.webp', 'is_image' => true, 'alt' => 'A "hero"', 'focal_point' => '40-30-1'],
        ['id' => TEMPLATE_ID_2, 'name' => 'second.webp', 'is_image' => true, 'alt' => 'Second'],
    ];
}

function renderFairuTemplate(string $template, string $language, bool $coalesce = false, array $data = []): string
{
    $data = array_merge(['id' => TEMPLATE_ID, 'ids' => [TEMPLATE_ID, TEMPLATE_ID_2]], $data);
    $render = fn () => $language === 'blade' ? Blade::render($template, $data) : (string) Antlers::parse($template, $data, true);
    if (! $coalesce) {
        return $render();
    }

    return (new CoalesceFairuMeta())->handle(Request::create('/'), fn () => new Response($render()))->getContent();
}

function invokeFairu(object $object, string $method, ...$args): mixed
{
    return (new ReflectionMethod($object, $method))->invokeArgs($object, $args);
}

