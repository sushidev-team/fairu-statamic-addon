<?php

namespace Sushidev\Fairu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Statamic\Facades\StaticCache;
use Sushidev\Fairu\Services\Fairu;
use Sushidev\Fairu\Services\FairuCache;
use Throwable;

/**
 * The Fairu utility: drop the metadata the site is holding, and see whether the
 * workspace answers at all.
 *
 * Both halves exist because of the same afternoon — a caption is fixed in Fairu,
 * the page keeps showing the old one, and the two things it could be are a cache
 * that has not expired yet and a connection that never worked. Until now the
 * answer to the first was `php artisan cache:clear`, which needs shell access
 * and takes the rest of the application's cache with it.
 */
class CacheUtilityController extends Controller
{
    /** What `purgeFairuCache` accepts in one call. Enough to paste a batch. */
    private const MAX_IDS = 50;

    /**
     * Props for the utility page. Read on every visit, so nothing here may cost
     * a round trip to Fairu — the connection check is a button for that reason.
     */
    public static function data(Request $request): array
    {
        [$stale, $expires] = static::ttl();

        return [
            'meta' => [
                'driver' => static::driver(),
                'stale' => $stale,
                'expires' => $expires,
                'version' => FairuCache::version(),
                'clearedAt' => FairuCache::clearedAt()?->diffForHumans(),
                'coalescing' => (bool) config('statamic.fairu.coalesce_meta'),
                'debug' => (bool) config('app.debug'),
            ],
            // `staticCache`, not `static`: the prop lands in a Vue template,
            // where `static` is a reserved word.
            'staticCache' => [
                'enabled' => (bool) config('statamic.static_caching.strategy'),
                'strategy' => config('statamic.static_caching.strategy'),
            ],
            'connection' => [
                'url' => config('statamic.fairu.url'),
                'proxy' => config('statamic.fairu.url_proxy'),
                'tenant' => static::maskedTenant(),
                'configured' => filled(config('statamic.fairu.connections.default.tenant'))
                    && filled(config('statamic.fairu.connections.default.tenant_secret')),
            ],
            'clearMetaUrl' => cp_route('utilities.fairu.clear-meta'),
            'testConnectionUrl' => cp_route('utilities.fairu.test-connection'),
            'purgeDeliveryUrl' => cp_route('utilities.fairu.purge-delivery'),
            'maxIds' => self::MAX_IDS,
        ];
    }

    public function clearMeta(Request $request)
    {
        FairuCache::flush();

        /*
         * A statically cached page has the old caption baked into its HTML, so
         * clearing the meta behind it changes nothing a visitor can see. Opt-in
         * rather than automatic: flushing static pages is expensive on a large
         * site and belongs to whoever asked for it.
         */
        if ($request->boolean('static') && config('statamic.static_caching.strategy')) {
            StaticCache::flush();

            return back()->withSuccess(__('fairu::utility.meta_and_static_cleared'));
        }

        return back()->withSuccess(__('fairu::utility.meta_cleared'));
    }

    /**
     * Purge the delivery cache in Fairu for the pasted ids.
     *
     * The other end of the cache story, and the one this addon cannot do by
     * itself: clearing what this site remembers about a file says nothing to the
     * proxy, the CDN, or the browser that already has the bytes. Answers as JSON
     * rather than as an Inertia redirect because the interesting part is the
     * two lists — what was queued, and what Fairu did not recognise.
     */
    public function purgeDelivery(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'string', 'max:4000'],
        ]);

        $ids = static::parseIds($validated['ids']);

        if ($ids->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => __('fairu::utility.no_ids'),
            ]);
        }

        if ($ids->count() > self::MAX_IDS) {
            return response()->json([
                'ok' => false,
                'message' => __('fairu::utility.too_many_ids', ['max' => self::MAX_IDS]),
            ]);
        }

        try {
            $result = (new Fairu)->purgeCache($ids->all());
        } catch (Throwable $ex) {
            return response()->json([
                'ok' => false,
                'message' => Str::limit($ex->getMessage(), 300),
            ]);
        }

        return response()->json([
            'ok' => true,
            'queued' => $result['queued'],
            'missing' => $result['missing'],
        ]);
    }

    /**
     * Ids arrive however they were copied — one per line, comma separated, or
     * pasted out of a log with the punctuation around them. Mirrors what the
     * /cache page in Fairu accepts, so the same paste works in both.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected static function parseIds(string $input)
    {
        return collect(preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $id) => trim($id, "\"'<>()[]"))
            ->filter(fn (string $id) => Str::isUuid($id))
            ->unique()
            ->values();
    }

    /**
     * Ask Fairu who we are.
     *
     * `api/users/scope` is the cheapest authenticated endpoint there is, and it
     * answers the question the page is really asking: does this tenant and this
     * key reach a workspace.
     */
    public function testConnection(Request $request)
    {
        try {
            $scope = (new Fairu)->getScopeFromEndpoint();
        } catch (Throwable $ex) {
            return response()->json([
                'ok' => false,
                'message' => Str::limit($ex->getMessage(), 300),
            ], 200);
        }

        return response()->json([
            'ok' => true,
            'tenant' => data_get($scope, 'tenant.name') ?? data_get($scope, 'name'),
            'abilities' => array_values((array) (data_get($scope, 'abilities') ?? data_get($scope, 'scope') ?? [])),
        ]);
    }

    /**
     * `caching_meta` is Laravel's flexible-cache pair: fresh until the first
     * number, then served stale and refreshed in the background until the
     * second. Both are minutes.
     */
    protected static function ttl(): array
    {
        $ttl = config('statamic.fairu.caching_meta', [60, 120]);

        if (! is_array($ttl)) {
            $ttl = [$ttl, $ttl];
        }

        return [(int) ($ttl[0] ?? 60), (int) ($ttl[1] ?? 120)];
    }

    protected static function driver(): string
    {
        $driver = config('cache.default');

        return $driver === 'statamic' ? 'file (statamic)' : (string) $driver;
    }

    /**
     * The tenant id identifies the workspace to anyone who can read the page,
     * and the page is open to everyone with the utility permission. Enough of
     * it to recognise which workspace, not enough to quote at support.
     */
    protected static function maskedTenant(): ?string
    {
        $tenant = config('statamic.fairu.connections.default.tenant');

        if (blank($tenant)) {
            return null;
        }

        return substr($tenant, 0, 8) . '…' . substr($tenant, -4);
    }
}
