<?php

namespace Sushidev\Fairu\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * One namespace for everything the addon caches about Fairu, and one way to
 * drop it.
 *
 * Meta is held for hours (see `caching_meta`), so a caption fixed in Fairu keeps
 * rendering stale on the site until the TTL runs out. Cache tags would let us
 * forget exactly those entries, but tags only exist on redis and memcached and
 * most Statamic installs run the file driver — a flush that only works on half
 * the installs is not a flush.
 *
 * So the version rides in the key instead. Bumping it orphans every entry the
 * addon has ever written, in one write, on any driver; the orphans then expire
 * on the schedule they were written with. Nothing else in the application cache
 * is touched, which is the whole point — `php artisan cache:clear` is the tool
 * this exists to avoid.
 */
class FairuCache
{
    /**
     * Deliberately outside the namespace it versions: the version has to
     * survive the bump that invalidates everything else.
     */
    public const VERSION_KEY = 'fairu.cache.version';

    public const CLEARED_AT_KEY = 'fairu.cache.cleared_at';

    /** Read once per request — every cache key in the addon goes through here. */
    protected static ?int $version = null;

    public static function version(): int
    {
        return static::$version ??= max(1, (int) Cache::get(self::VERSION_KEY, 1));
    }

    /**
     * Namespace a cache key.
     *
     * Also fixes a smaller problem the addon had on the way past: several of
     * these keys were a bare md5 of the tag parameters, which is a name any
     * other package could pick as well.
     */
    public static function key(string $key): string
    {
        return 'fairu.v' . static::version() . '.' . $key;
    }

    /**
     * Orphan everything under the current namespace. Returns the new version.
     */
    public static function flush(): int
    {
        $version = static::version() + 1;

        Cache::forever(self::VERSION_KEY, $version);
        Cache::forever(self::CLEARED_AT_KEY, Carbon::now()->toIso8601String());

        static::$version = $version;

        return $version;
    }

    public static function clearedAt(): ?CarbonInterface
    {
        $value = Cache::get(self::CLEARED_AT_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    /** Testing seam — the memoised version would otherwise outlive a store swap. */
    public static function forgetMemoizedVersion(): void
    {
        static::$version = null;
    }
}
