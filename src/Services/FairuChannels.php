<?php

namespace Sushidev\Fairu\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reading video and audio channels — shows, seasons, episodes — out of a
 * workspace.
 *
 * The public queries are the default, not the authenticated ones, and that is
 * the important decision in this file. The addon holds a workspace API key, so
 * `fairuVideoChannel` would happily hand a template the episodes that are still
 * drafts and the ones whose release window has not opened — and a template that
 * loops over `episodes` has no way of knowing it was given more than a visitor
 * may see. `fairuPublicVideoChannel` marks what it returns, and the API narrows
 * seasons and episodes to the published ones on the way out whatever key was
 * presented. Ask for the authenticated view explicitly (`preview="true"`) when
 * that is genuinely what is wanted, in a live preview or a CP screen.
 */
class FairuChannels
{
    /** Marks a fetch that failed, so that it can be kept out of the cache. */
    private const FAILED = '__fairu_failed';

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

    private const EMBED_FIELDS = 'embed(width: $embedWidth) { url html iframe width height aspect_ratio }';

    private const EPISODE_FIELDS = <<<'GRAPHQL'
        id
        number
        title
        description
        show_notes
        published_at
        episode_type
        explicit
        orientation
        aspect_ratio
    GRAPHQL;

    public function __construct(protected string $connection = 'default') {}

    /**
     * One channel, by id.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id, array $options = []): ?array
    {
        $preview = (bool) data_get($options, 'preview', false);

        $field = $preview
            ? 'fairuVideoChannel(id: $id)'
            : 'fairuPublicVideoChannel(id: $id)';

        $query = sprintf(
            'query FairuChannel($id: ID!, $embedWidth: Int) { %s { %s } }',
            $field,
            $this->channelFields($options)
        );

        $data = $this->fetch('channel', $query, [
            'id' => $id,
            'embedWidth' => $this->embedWidth($options),
        ]);

        return data_get($data, $preview ? 'fairuVideoChannel' : 'fairuPublicVideoChannel');
    }

    /**
     * One channel, by the name it is addressed with.
     *
     * A slug is unique inside a workspace and nowhere else, so the public query
     * needs the workspace named before the slug means anything — which is what
     * lets a site route `/podcasts/die-werkstatt` instead of carrying a uuid in
     * every link.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, array $options = []): ?array
    {
        $preview = (bool) data_get($options, 'preview', false);
        $tenant = $this->tenant();

        if (! $preview && blank($tenant)) {
            return null;
        }

        /*
         * The declaration list is built alongside the field, not written out in
         * full: GraphQL rejects a document that declares a variable it does not
         * use, so `$tenant` may only be named in the branch that passes it.
         */
        $field = $preview
            ? 'fairuVideoChannelBySlug(slug: $slug)'
            : 'fairuPublicVideoChannelBySlug(tenant: $tenant, slug: $slug)';

        $declaration = $preview
            ? '$slug: String!, $embedWidth: Int'
            : '$slug: String!, $tenant: ID!, $embedWidth: Int';

        $query = sprintf(
            'query FairuChannelBySlug(%s) { %s { %s } }',
            $declaration,
            $field,
            $this->channelFields($options)
        );

        $variables = ['slug' => $slug, 'embedWidth' => $this->embedWidth($options)];

        if (! $preview) {
            $variables['tenant'] = $tenant;
        }

        $data = $this->fetch('channel-slug', $query, $variables);

        return data_get($data, $preview ? 'fairuVideoChannelBySlug' : 'fairuPublicVideoChannelBySlug');
    }

    /**
     * The channels of this workspace, for a shelf or an index page.
     *
     * The public list leaves out what the workspace publishes without
     * advertising (`exclude_from_list`) — the two queries that address a channel
     * directly still answer for those, because whoever holds the address was
     * given it.
     *
     * @return array<string, mixed>|null
     */
    public function all(array $options = []): ?array
    {
        $preview = (bool) data_get($options, 'preview', false);
        $tenant = $this->tenant();

        if (! $preview && blank($tenant)) {
            return null;
        }

        $field = $preview
            ? 'fairuVideoChannels(page: $page, perPage: $perPage, search: $search)'
            : 'fairuPublicVideoChannels(tenant: $tenant, page: $page, perPage: $perPage, search: $search)';

        $assetFields = self::ASSET_FIELDS;

        // No `embed` in this selection, so no `$embedWidth` to declare — an
        // unused variable definition is a document GraphQL refuses.
        $declaration = $preview
            ? '$page: Int, $perPage: Int, $search: String'
            : '$tenant: ID!, $page: Int, $perPage: Int, $search: String';

        $query = <<<GRAPHQL
            query FairuChannels($declaration) {
                $field {
                    data {
                        id
                        name
                        slug
                        kind
                        description
                        author
                        explicit
                        cover_image { $assetFields }
                    }
                    paginatorInfo { count currentPage lastPage perPage total hasMorePages }
                }
            }
        GRAPHQL;

        $variables = [
            'page' => data_get($options, 'page') !== null ? (int) data_get($options, 'page') : null,
            'perPage' => (int) (data_get($options, 'perPage') ?? data_get($options, 'limit') ?? 25),
            'search' => data_get($options, 'search'),
        ];

        if (! $preview) {
            $variables['tenant'] = $tenant;
        }

        $data = $this->fetch('channels', $query, $variables);

        return data_get($data, $preview ? 'fairuVideoChannels' : 'fairuPublicVideoChannels');
    }

    /**
     * The channel's podcast feed, which is a plain URL rather than a query.
     *
     * Not on the channel type — the API has no field for it — but every show
     * page needs it, both as the address people paste into a podcast app and as
     * the `<link rel="alternate">` that lets an app find it on its own.
     */
    public function feedUrl(string $id): string
    {
        return rtrim((string) config('statamic.fairu.url'), '/') . '/channels/' . $id . '/feed.xml';
    }

    /**
     * Seasons are asked for separately because they carry their episodes a
     * second time: a channel that renders as a flat list should not pay for the
     * nesting, and one that renders season by season should not pay for the
     * flat list.
     */
    protected function channelFields(array $options): string
    {
        $assetFields = self::ASSET_FIELDS;
        $episodeFields = self::EPISODE_FIELDS;
        $embed = self::EMBED_FIELDS;

        $episode = <<<GRAPHQL
            $episodeFields
            $embed
            asset { $assetFields }
        GRAPHQL;

        $seasons = filter_var(data_get($options, 'seasons', false), FILTER_VALIDATE_BOOLEAN)
            ? "seasons { id number name description episodes { $episode } }"
            : '';

        $episodes = filter_var(data_get($options, 'episodes', true), FILTER_VALIDATE_BOOLEAN)
            ? "episodes { $episode }"
            : '';

        return <<<GRAPHQL
            id
            name
            slug
            kind
            description
            author
            copyright
            itunes_type
            itunes_category
            itunes_subcategory
            explicit
            enforced_format
            cover_image { $assetFields }
            player_settings {
                autoplay
                muted
                autoplay_next
                repeat
                fullscreen
                picture_in_picture
                cast
                quality_selector
                subtitles
                volume_control
                playback_speed
                skip_buttons
                allow_seeking
                auto_hide_controls
                show_playlist
                show_chapters
                controls_hide_delay
                skip_forward_seconds
                skip_backward_seconds
                pause_on_hidden
                resume_on_visible
                accent_color
            }
            $embed
            $seasons
            $episodes
        GRAPHQL;
    }

    protected function embedWidth(array $options): ?int
    {
        $width = data_get($options, 'embedWidth') ?? data_get($options, 'width');

        return $width !== null ? (int) $width : null;
    }

    protected function tenant(): ?string
    {
        return config('statamic.fairu.connections.' . $this->connection . '.tenant');
    }

    /**
     * Episodes move more often than files do — one goes live every Thursday
     * morning — so they follow their own TTL rather than the metadata one.
     */
    protected function fetch(string $kind, string $query, array $variables): array
    {
        $key = FairuCache::key($kind . '-' . md5($this->connection . json_encode($variables)));

        $result = (array) Cache::flexible(
            $key,
            config('app.debug') ? [0, 0] : config('statamic.fairu.caching_channels', config('statamic.fairu.caching_meta')),
            function () use ($query, $variables) {
                try {
                    return (new Fairu($this->connection))->graphql($query, $variables);
                } catch (Throwable $ex) {
                    Log::error('Fairu: channel query failed: ' . $ex->getMessage());

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
