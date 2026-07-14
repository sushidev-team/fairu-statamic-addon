<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Sushidev\Fairu\Services\Fairu;

/**
 * Regression cover for uploadFile(). The PUT result was previously read
 * outside the try/catch scope, so any thrown request (or a missing upload
 * URL) surfaced as "Undefined variable $resultUpload" instead of a clean
 * null return. Every failure path must now return null without touching an
 * undefined variable.
 */

const UPLOAD_URL = 'https://upload.example/put/object';
const SYNC_URL = 'https://sync.example/sync/object';
const FILE_ID = '22222222-2222-2222-2222-222222222222';

function fakeUploadLink(): array
{
    return [
        'data' => [
            'createFairuUploadLink' => [
                'id' => FILE_ID,
                'mime' => 'image/webp',
                'upload_url' => UPLOAD_URL,
                'sync_url' => SYNC_URL,
            ],
        ],
    ];
}

it('returns the file id and triggers sync on a successful upload', function () {
    Http::fake([
        'fairu.app/graphql' => Http::response(fakeUploadLink()),
        UPLOAD_URL => Http::response('', 200),
        SYNC_URL => Http::response('', 200),
    ]);

    $id = (new Fairu())->uploadFile('bytes', 'hero.webp', null);

    expect($id)->toBe(FILE_ID);
    Http::assertSent(fn ($request) => $request->url() === SYNC_URL);
});

it('returns null and skips the PUT when no upload URL is returned', function () {
    Http::fake([
        'fairu.app/graphql' => Http::response(['data' => ['createFairuUploadLink' => []]]),
    ]);

    $id = (new Fairu())->uploadFile('bytes', 'hero.webp', null);

    expect($id)->toBeNull();
    Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
});

it('returns null when the upload request throws instead of raising undefined variable', function () {
    Http::fake([
        'fairu.app/graphql' => Http::response(fakeUploadLink()),
        UPLOAD_URL => fn () => throw new ConnectionException('connection reset'),
    ]);

    $id = (new Fairu())->uploadFile('bytes', 'hero.webp', null);

    expect($id)->toBeNull();
});

it('returns null when the upload responds with a non-200 status', function () {
    Http::fake([
        'fairu.app/graphql' => Http::response(fakeUploadLink()),
        UPLOAD_URL => Http::response('forbidden', 403),
    ]);

    $id = (new Fairu())->uploadFile('bytes', 'hero.webp', null);

    expect($id)->toBeNull();
    Http::assertNotSent(fn ($request) => $request->url() === SYNC_URL);
});
