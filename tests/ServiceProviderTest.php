<?php

use Illuminate\Support\Facades\File;

/**
 * Regression test: the addon must never register a non-existent view path.
 *
 * Previously bootAddon() called loadViewsFrom() unconditionally on
 * resources/views, which does not always exist in the published package.
 * That caused "The ... /resources/views directory does not exist." when the
 * view finder resolved the hint via realpath().
 */
it('does not register a missing view path', function () {
    $hints = view()->getFinder()->getHints();

    foreach ($hints as $namespace => $paths) {
        foreach ($paths as $path) {
            expect(File::isDirectory($path))->toBeTrue(
                "View namespace [{$namespace}] points to a missing directory: {$path}"
            );
        }
    }
});

it('boots without a views directory', function () {
    $viewsPath = realpath(__DIR__ . '/..') . '/resources/views';

    if (! File::isDirectory($viewsPath)) {
        $hints = view()->getFinder()->getHints();
        expect($hints['fairu'] ?? [])->not->toContain($viewsPath);
    }

    expect($this->app->isBooted())->toBeTrue();
});

it('registers the replacement asset navigation and creates its view directory', function () {
    config(['statamic.fairu.deactivate_old' => true]);
    \Illuminate\Support\Facades\Route::name('statamic.cp.')->group(__DIR__.'/../routes/cp.php');
    app('router')->getRoutes()->refreshNameLookups();
    $path = base_path('resources/views/vendor/sushidev-fairu');
    // Use the real filesystem, while capturing the navigation extension for inspection.
    \Statamic\Facades\CP\Nav::shouldReceive('extend')->once()->andReturnUsing(function ($callback) {
        $nav = Mockery::mock();
        $nav->shouldReceive('remove')->once()->with('Content', 'Assets');
        $nav->shouldReceive('content')->once()->with('Assets')->andReturnSelf();
        $nav->shouldReceive('url')->once()->with(cp_route('fairu.browser'))->andReturnSelf();
        $nav->shouldReceive('icon')->once()->with('assets')->andReturnSelf();
        $nav->shouldReceive('can')->once()->with('view fairu assets')->andReturnSelf();
        $callback($nav);
    });
    try {
        (new \Sushidev\Fairu\ServiceProvider(app()))->bootAddon();
        expect(File::isDirectory($path))->toBeTrue();
    } finally {
        File::deleteDirectory($path);
    }
});
