<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\User;
use Sushidev\Fairu\Http\Controllers\AssetController;
use Symfony\Component\HttpKernel\Exception\HttpException;

function allowFairuActions(bool $super = true, bool $allowed = true): void
{
    $user = Mockery::mock(\Statamic\Contracts\Auth\User::class);
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturn($allowed);
    User::shouldReceive('current')->andReturn($user);
}

dataset('rest actions', [
    ['folderContent', '/api/folders/folder', 'GET'],
    ['upload', '/api/files', 'POST'],
    ['uploadMetaBulk', '/api/upload/meta/bulk', 'POST'],
    ['createFolder', '/api/folders', 'POST'],
    ['updateFolder', '/api/folders/id', 'PUT'],
    ['getFile', '/api/files/id', 'GET'],
    ['getFilesList', '/api/files/list', 'POST'],
    ['getFolder', '/api/folders/id', 'GET'],
]);

it('forwards REST actions with tenant credentials', function ($method, $path, $verb) {
    allowFairuActions(false);
    config(['statamic.fairu.connections.default.tenant_secret' => 'test-secret']);
    Http::fake(['*' => Http::response(['id' => 'result'])]);
    $request = Request::create('/', 'POST', ['name' => 'Name', 'filename' => 'file.webp', 'folder' => 'folder', 'ids' => ['id'], 'files' => ['id']]);
    $result = (new AssetController())->$method($request, 'id');
    expect($result)->toBe(['id' => 'result']);
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://fairu.app'.$path) && $r->method() === $verb && $r->hasHeader('Tenant', 'first') && $r->hasHeader('Authorization', 'Bearer test-secret'));
})->with('rest actions');

it('reports REST failures', function ($method, $path, $verb, $status) {
    allowFairuActions();
    Http::fake(['*' => Http::response([], $status)]);
    try {
        (new AssetController())->$method(Request::create('/'), 'id');
        test()->fail('Expected failure');
    } catch (HttpException $e) {
        $subscription = in_array($method, ['folderContent', 'getFile', 'getFilesList', 'getFolder']) && $status === 403;
        expect($e->getStatusCode())->toBe($subscription ? 403 : 400);
    }
})->with('rest actions')->with([403, 500]);

it('rejects unauthorized actions before reaching Fairu', function ($guest) {
    if ($guest) {
        User::shouldReceive('current')->andReturnNull();
    } else {
        allowFairuActions(false, false);
    }
    expect(fn () => (new AssetController())->upload(Request::create('/')))->toThrow(HttpException::class);
    Http::assertNothingSent();
})->with([true, false]);

it('returns browser configuration', function () {
    allowFairuActions();
    $response = Mockery::mock(\Inertia\Response::class);
    \Inertia\Inertia::shouldReceive('render')->once()->with('fairu/Browser', Mockery::on(fn ($props) => $props['title'] === 'Assets' && $props['config']['allow_uploads']))->andReturn($response);
    expect((new AssetController())->browser())->toBe($response);
});

it('handles bulk uploads and transport errors', function ($status) {
    allowFairuActions();
    Http::fake(fn () => $status === 0 ? throw new RuntimeException('offline') : Http::response(['files' => ['uploaded']], $status));
    $response = (new AssetController())->uploadMultiple(Request::create('/', 'POST', ['files' => ['file.webp']]));
    expect($response->status())->toBe($status ?: 500);
    expect($response->getData(true))->toHaveKey($status === 200 ? 'files' : 'error');
})->with([200, 422, 0]);

it('validates bulk uploads and renames', function ($method) {
    allowFairuActions();
    expect(fn () => (new AssetController())->$method(Request::create('/'), 'id'))->toThrow(\Illuminate\Validation\ValidationException::class);
    Http::assertNothingSent();
})->with(['uploadMultiple', 'renameFile']);

it('forwards GraphQL mutations with expected variables', function ($method, $key, $variables) {
    allowFairuActions();
    Http::fake(['*/graphql' => Http::response(['data' => [$key => true]])]);
    $result = (new AssetController())->$method(Request::create('/', 'POST', ['name' => 'renamed.webp', 'alt' => '', 'caption' => null, 'parent' => '']), 'id');
    expect($result)->toBe(['data' => true]);
    Http::assertSent(fn ($r) => $r['variables'] === $variables);
})->with([
    ['updateFile', 'updateFairuFile', ['data' => ['id' => 'id', 'alt' => '']]],
    ['deleteFile', 'deleteFairuFile', ['id' => 'id']],
    ['renameFile', 'renameFairuFile', ['id' => 'id', 'name' => 'renamed.webp']],
    ['moveFile', 'moveFairuFile', ['id' => 'id', 'parent' => null]],
]);

it('reports GraphQL transport and validation errors', function ($status) {
    allowFairuActions();
    Http::fake(['*/graphql' => Http::response(['errors' => [['message' => 'Invalid file']]], $status)]);
    if ($status === 200) {
        $response = (new AssetController())->deleteFile(Request::create('/'), 'id');
        expect($response->status())->toBe(422)->and($response->getData(true)['message'])->toBe('Invalid file');
    } else {
        try {
            (new AssetController())->deleteFile(Request::create('/'), 'id');
            test()->fail('Expected HTTP error');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe($status === 403 ? 403 : 400);
        }
    }
})->with([200, 403, 500]);

it('streams original bytes with an attachment disposition', function ($name) {
    config(['statamic.fairu.url_proxy' => 'https://files.example/']);
    Http::fake(['*' => Http::response(str_repeat('pdf', 4000), 200, ['Content-Type' => 'application/pdf'])]);
    $response = (new AssetController())->download(Request::create('/'), TEMPLATE_ID, $name);
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect($content)->toBe(str_repeat('pdf', 4000))
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment', $name ?? 'download');
    Http::assertSent(fn ($r) => $r->url() === 'https://files.example/'.TEMPLATE_ID.'/'.($name ?? 'file'));
})->with(['plan.pdf', null]);

it('rejects missing downloads', function ($id, $status) {
    Http::fake(['*' => Http::response('', $status)]);
    expect(fn () => (new AssetController())->download(Request::create('/'), $id))->toThrow(HttpException::class);
})->with([['', 200], [TEMPLATE_ID, 404]]);
