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
