<?php

namespace Sushidev\Fairu\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reading galleries out of a workspace.
 *
 * A gallery is the piece of Fairu that a Statamic site has been rebuilding by
 * hand: a folder someone curated, with a cover, a date, a place and the
 * copyrights already attached. Until now the only way to put one on a page was
 * to paste two hundred ids into a `fairu` field and re-sort them there.
 *
 * Everything is read through one GraphQL document per call, because that is the
 * whole point of using GraphQL here — a cover, the items and the copyright line
 * are one round trip, not three.
 */
class FairuGalleries
{
    /** Marks a fetch that failed, so that it can be kept out of the cache. */
    private const FAILED = '__fairu_failed';

    /**
     * Asset fields every item needs to render. Deliberately not `url`: the
     * addon builds URLs itself so that `sources`, `ratio`, `fit` and `format`
     * behave exactly as they do on `{{ fairu:image }}`, transform for transform.
     */
    private const ASSET_FIELDS = <<<'GRAPHQL'
        id
        name
        alt
        caption
        mime
        width
        height
        focal_point
        blurhash
        duration
    GRAPHQL;

    public function __construct(protected string $connection = 'default') {}

    /**
     * One gallery with its items.
     *
     * `page` switches from `items` to `itemsPaginated`, which is the same list
     * with a paginator beside it — a gallery of four hundred photographs is a
     * page of thirty and a link to the next, not one response.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id, array $options = []): ?array
    {
        $page = data_get($options, 'page');
        $perPage = (int) (data_get($options, 'perPage') ?? data_get($options, 'limit') ?? 50);
        $orderBy = data_get($options, 'orderBy');
        $orderDirection = data_get($options, 'orderDirection');

        // Heredocs interpolate variables, not class constants.
        $assetFields = self::ASSET_FIELDS;

        /*
         * The declaration list follows the selection rather than being written
         * out in full: GraphQL refuses a document that declares a variable it
         * never uses, and the plain `items` branch uses two of the five.
         */
        $items = $page !== null
            ? sprintf(
                'itemsPaginated(page: $page, perPage: $perPage, orderBy: $orderBy, orderDirection: $orderDirection) { data { %s } paginatorInfo { count currentPage lastPage perPage total hasMorePages } }',
                self::ASSET_FIELDS
            )
            : sprintf('items(first: $perPage) { %s }', self::ASSET_FIELDS);

        $declaration = $page !== null
            ? '$id: ID!, $page: Int, $perPage: Int, $orderBy: String, $orderDirection: String'
            : '$id: ID!, $perPage: Int';

        $query = <<<GRAPHQL
            query FairuGallery($declaration) {
                fairuGallery(id: \$id) {
                    id
                    name
                    description
                    date
                    location
                    copyright_text
                    copyrights { id name }
                    sorting_field
                    sorting_direction
                    cover_image { $assetFields }
                    $items
                }
            }
        GRAPHQL;

        $variables = ['id' => $id, 'perPage' => $perPage];

        if ($page !== null) {
            $variables['page'] = (int) $page;
            $variables['orderBy'] = $orderBy;
            $variables['orderDirection'] = $orderDirection;
        }

        return data_get($this->fetch('gallery', $query, $variables), 'fairuGallery');
    }

    /**
     * The galleries of this workspace, for an index page.
     *
     * `tenants` is required by the API and supplied from the connection —
     * `fairuGalleries` was written for a surface that lists several workspaces
     * at once, and a site only ever has its own.
     *
     * @return array<string, mixed>|null
     */
    public function all(array $options = []): ?array
    {
        $assetFields = self::ASSET_FIELDS;

        $tenant = config('statamic.fairu.connections.' . $this->connection . '.tenant');

        if (blank($tenant)) {
            return null;
        }

        $query = <<<GRAPHQL
            query FairuGalleries(
                \$tenants: [ID!]!
                \$page: Int
                \$perPage: Int
                \$search: String
                \$from: String
                \$until: String
                \$orderBy: String
                \$orderDirection: String
            ) {
                fairuGalleries(
                    tenants: \$tenants
                    page: \$page
                    perPage: \$perPage
                    search: \$search
                    from: \$from
                    until: \$until
                    orderBy: \$orderBy
                    orderDirection: \$orderDirection
                ) {
                    data {
                        id
                        name
                        description
                        date
                        location
                        copyright_text
                        cover_image { $assetFields }
                    }
                    paginatorInfo { count currentPage lastPage perPage total hasMorePages }
                }
            }
        GRAPHQL;

        $variables = [
            'tenants' => [$tenant],
            'page' => data_get($options, 'page') !== null ? (int) data_get($options, 'page') : null,
            'perPage' => (int) (data_get($options, 'perPage') ?? data_get($options, 'limit') ?? 25),
            'search' => data_get($options, 'search'),
            'from' => data_get($options, 'from'),
            'until' => data_get($options, 'until'),
            'orderBy' => data_get($options, 'orderBy'),
            'orderDirection' => data_get($options, 'orderDirection'),
        ];

        return data_get($this->fetch('galleries', $query, $variables), 'fairuGalleries');
    }

    /**
     * Cached like every other read in the addon, and failing the same way: a
     * template asking for a gallery while Fairu is unreachable renders nothing
     * rather than a 500 — the page around it is usually more important than the
     * photographs on it.
     */
    protected function fetch(string $kind, string $query, array $variables): array
    {
        $key = FairuCache::key($kind . '-' . md5($this->connection . json_encode($variables)));

        $result = (array) Cache::flexible(
            $key,
            config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'),
            function () use ($query, $variables) {
                try {
                    return (new Fairu($this->connection))->graphql($query, $variables);
                } catch (Throwable $ex) {
                    Log::error('Fairu: gallery query failed: ' . $ex->getMessage());

                    return [self::FAILED => true];
                }
            }
        );

        /*
         * A failure must not be cached. `Cache::flexible` keeps whatever the
         * closure returns, so a minute of Fairu being unreachable would
         * otherwise leave the site rendering empty pages for the rest of the
         * TTL — long after the thing that broke had been fixed.
         */
        if (data_get($result, self::FAILED)) {
            Cache::forget($key);

            return [];
        }

        return $result;
    }

}
