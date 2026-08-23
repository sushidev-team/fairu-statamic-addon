<?php

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Utility;
use Sushidev\Fairu\Services\FairuCache;

beforeEach(function () {
    FairuCache::forgetMemoizedVersion();
});

it('namespaces every cache key with the current version', function () {
    expect(FairuCache::key('demo'))->toBe('fairu.v1.demo');

    FairuCache::flush();

    expect(FairuCache::key('demo'))->toBe('fairu.v2.demo');
});

/**
 * The whole point of versioning the keys: a flush has to work on the file
 * driver too, where cache tags do not exist.
 */
it('orphans everything cached under the previous version', function () {
    Cache::put(FairuCache::key('meta'), 'stale caption', 600);

    expect(Cache::get(FairuCache::key('meta')))->toBe('stale caption');

    FairuCache::flush();

    expect(Cache::get(FairuCache::key('meta')))->toBeNull();
});

it('leaves the rest of the application cache alone', function () {
    Cache::put('someone-elses-key', 'value', 600);

    FairuCache::flush();

    expect(Cache::get('someone-elses-key'))->toBe('value');
});

it('records when it was last cleared', function () {
    expect(FairuCache::clearedAt())->toBeNull();

    FairuCache::flush();

    expect(FairuCache::clearedAt())->not->toBeNull();
});

it('registers the Fairu utility with its routes', function () {
    $utility = Utility::boot()->find('fairu');

    expect($utility)->not->toBeNull();
    expect($utility->slug())->toBe('fairu');
    expect($utility->inertia())->toBe('fairu/CacheUtility');
    expect($utility->routes())->not->toBeNull();
});

it('renders the utility page for a permitted user', function () {
    $user = Statamic\Facades\User::make()->id('utility-test')->makeSuper();

    $this->actingAs($user)
        ->get(cp_route('utilities.fairu'))
        ->assertOk();
});

it('clears the cache from the utility route', function () {
    $user = Statamic\Facades\User::make()->id('purge-test')->makeSuper();

    Cache::put(FairuCache::key('meta'), 'stale caption', 600);

    $this->actingAs($user)
        ->post(cp_route('utilities.fairu.clear-meta'))
        ->assertRedirect();

    FairuCache::forgetMemoizedVersion();

    expect(Cache::get(FairuCache::key('meta')))->toBeNull();
});
