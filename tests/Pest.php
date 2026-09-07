<?php

use Sushidev\Fairu\Tests\TestCase;

require_once __DIR__.'/TemplateHelpers.php';

uses(TestCase::class)->beforeEach(function () {
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    config(['statamic.fairu.connections.default.tenant' => 'first']);
})->in(__DIR__);

require_once __DIR__.'/CommandHelpers.php';
