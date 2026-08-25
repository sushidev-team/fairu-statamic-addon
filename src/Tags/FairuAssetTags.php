<?php

namespace Sushidev\Fairu\Tags;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Statamic\Tags\Tags;
use Sushidev\Fairu\Services\Fairu;
use Sushidev\Fairu\Services\FairuCache;
use Sushidev\Fairu\Services\FairuChannels;
use Sushidev\Fairu\Services\FairuGalleries;
use Sushidev\Fairu\Services\FairuMetaBag;
use Sushidev\Fairu\Traits\TransformAssets;

class FairuAssetTags extends Tags
{
    use TransformAssets;

    protected static $handle = 'fairu';

    /**
     * The {{ fairu:url }} tag.
     *
     * @return array
     */
    public function url()
    {
        $id = Arr::get($this->resolveIds($this->params->get('id')), 0);

        $id = $this->fairu->parse($id);

        $filename = $this->params->get('name');

        $fetchMeta = filter_var($this->fetchMetaParam(), FILTER_VALIDATE_BOOLEAN);

        if (! $filename && $id !== null && $fetchMeta) {
            $bag = app(FairuMetaBag::class);
            if ($bag->shouldDefer()) {
                return $bag->queue('url', $id, $this->params->toArray(), $this->getConnectionName());
            }

            $asset = $this->getFile($id, true);
            $filename = data_get($asset, 'name');
        }

        if ($this->wantsDownload()) {
            return $this->downloadUrl($id, $filename ?? 'file');
        }

        return $this->getUrl(
            id: $id,
            filename: $filename ?? 'file',
            appendQuery: true
        );
    }

    /**
     * The {{ fairu }} tag.
     *
     * @return array
     */
    public function index()
    {
        $cacheKey = FairuCache::key('list-' . md5(json_encode($this->params->toArray())));

        $ids = $this->params->get('id') ?? $this->params->get('ids');
        $ids = $this->resolveIds($ids);

        $fetchMeta = $this->fetchMetaParam();

        // Coalesce: when active AND meta is actually being fetched, defer the
        // whole list tag (body + ids) so the middleware can resolve every id on
        // the page in one batched /api/files/meta call and re-render each body.
        if (is_array($ids) && ! empty($ids) && filter_var($fetchMeta, FILTER_VALIDATE_BOOLEAN)) {
            $bag = app(FairuMetaBag::class);
            if ($bag->shouldDefer() && is_string($this->content) && $this->content !== '') {
                $params = $this->params->toArray();
                $params['_ids'] = $ids;

                return $bag->queueList(
                    ids: $ids,
                    params: $params,
                    body: $this->content,
                    context: $this->context?->all() ?? [],
                    connection: $this->getConnectionName(),
                );
            }
        }

        $files = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids, $fetchMeta) {
            return collect($this->getFiles($ids, $fetchMeta))->map(function ($asset) {
                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                    fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                    appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp'),
                );
                $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));
                if (!empty($srcset_entries)) {
                    data_set($asset, 'srcset', implode(", ", $srcset_entries));
                }
                data_set($asset, 'url', $url);
                data_set($asset, 'focus_css', $this->formatFocalPoint($this->params->get('focal_point') ?? data_get($asset, 'focal_point')));

                return $asset;
            });
        });

        return $files;
    }

    /**
     * The {{ fairu:image }} tag.
     *
     * @return string
     */
    public function image()
    {

        $cacheKey = FairuCache::key('image-' . md5(json_encode($this->params->toArray())));

        $id = Arr::get($this->resolveIds($this->params->get('id')), 0);
        if (!$id) {
            return;
        }

        $bag = app(FairuMetaBag::class);
        if ($bag->shouldDefer()) {
            return $bag->queue('image', $id, $this->params->toArray(), $this->getConnectionName());
        }

        $fetchMeta = $this->fetchMetaParam();

        return Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($id, $fetchMeta) {
            $asset = $this->getFile($id, $fetchMeta);
            $url = $this->getUrl(
                id: data_get($asset, 'id'),
                filename: $this->params->get('name') ?? data_get($asset, 'name'),
                focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp')
            );
            data_set($asset, 'url', $url);

            $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));

            $altText = $this->params->get('alt') ?? data_get($asset, 'description');

            $image_params = [
                !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                !empty($this->params->get('alt')) ? "alt='" . strip_tags($altText) . "'" : null,
                !empty($this->params->get('sizes')) ? "sizes='" . $this->params->get('sizes') . "'" : null,
                !empty($srcset_entries) ? "srcset='" . implode(", ", $srcset_entries) . "'" : null,
            ];

            $image_params = array_filter($image_params);
            $attributes = implode(' ', $image_params);

            return "<img src='$url' $attributes>";
        });
    }

    /**
     * The {{ fairu:images }} tag.
     *
     * @return array
     */
    public function images()
    {

        $cacheKey = FairuCache::key('images-' . md5(json_encode($this->params->toArray())));

        $ids = $this->resolveIds($this->params->get('ids'));
        if (empty($ids)) {
            return;
        }

        $fetchMeta = $this->fetchMetaParam();

        $imgStrings = Cache::flexible($cacheKey, config('app.debug') ? [0, 0] : config('statamic.fairu.caching_meta'), function () use ($ids, $fetchMeta) {
            return collect($this->getFiles($ids, $fetchMeta))->map(function ($asset) {
                $url = $this->getUrl(
                    id: data_get($asset, 'id'),
                    filename: $this->params->get('name') ?? data_get($asset, 'name'),
                    focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
                    fit: $this->params->get('fit') ?? data_get($asset, 'fit'),
                    appendQuery: data_get($asset, 'is_image') || $this->params->get('width') || $this->params->get('height') || $this->params->get('sources') || $this->params->get('timestamp')
                );
                data_set($asset, 'url', $url);

                $srcset_entries = $this->getSources($asset, $this->params->get('sources'), $this->params->get('name'), $this->params->get('ratio'));

                $altText = $this->params->get('alt') ?? data_get($asset, 'description');

                $image_params = [
                    !empty($this->params->get('width')) ? "width='" . $this->params->get('width') . "'" : null,
                    !empty($this->params->get('height')) ? "height='" . $this->params->get('height') . "'" : null,
                    !empty($this->params->get('class')) ? "class='" . $this->params->get('class') . "'" : null,
                    !empty($altText) ? "alt='" . strip_tags($altText) . "'" : null,
                    !empty($this->params->get('sizes')) ? "sizes='" . $this->params->get('sizes') . "'" : null,
                    !empty($srcset_entries) ? "srcset='" . implode(", ", $srcset_entries) . "'" : null,
                ];

                $image_params = array_filter($image_params);
                $attributes = implode(' ', $image_params);

                return "<img src='$url' $attributes>";
            });
        });

        return $imgStrings?->implode('');
    }

    /**
     * The {{ fairu:gallery }} tag.
     *
     * A gallery is curated in Fairu — sorted, given a cover, a date, a place and
     * its copyrights — and a Statamic site has had no way to say so: the same
     * gallery had to be rebuilt as a `fairu` field holding two hundred ids, kept
     * in order by hand, with the copyright line typed underneath.
     *
     * Renders its body once with the gallery in scope, so `{{ items }}` reads
     * exactly like `{{ fairu }}` does — every transform parameter on this tag
     * applies to every item of it.
     *
     * @return array|null
     */
    public function gallery()
    {
        // Not through `resolveIds`: that turns anything which is not a uuid into
        // one by hashing an old asset path, which is right for a file id and
        // nonsense for a gallery.
        $id = (string) $this->params->get('id');

        if (! Str::isUuid($id)) {
            return null;
        }

        $gallery = (new FairuGalleries($this->getConnectionName()))->find($id, [
            'page' => $this->params->get('page'),
            'perPage' => $this->params->get('perPage') ?? $this->params->get('per_page'),
            'limit' => $this->params->get('limit'),
            'orderBy' => $this->params->get('orderBy') ?? $this->params->get('order_by'),
            'orderDirection' => $this->params->get('orderDirection') ?? $this->params->get('order_direction'),
        ]);

        if (! is_array($gallery)) {
            return null;
        }

        /*
         * `itemsPaginated` and `items` are the same list under two names. They
         * are flattened into one `items` here so that a template does not have
         * to know which one the parameters happened to select, and the paginator
         * is offered beside it under Statamic's own name.
         */
        $paginated = data_get($gallery, 'itemsPaginated');

        $items = $paginated !== null
            ? (array) data_get($paginated, 'data', [])
            : (array) data_get($gallery, 'items', []);

        $gallery['items'] = collect($items)->map(fn ($item) => $this->augmentFairuAsset($item))->filter()->values()->all();
        $gallery['total_items'] = data_get($paginated, 'paginatorInfo.total') ?? count($gallery['items']);
        $gallery['cover_image'] = $this->augmentFairuAsset(data_get($gallery, 'cover_image'));
        $gallery['paginate'] = data_get($paginated, 'paginatorInfo');

        unset($gallery['itemsPaginated']);

        return $gallery;
    }

    /**
     * The {{ fairu:galleries }} tag — the index page of the above.
     *
     * @return array
     */
    public function galleries()
    {
        $result = (new FairuGalleries($this->getConnectionName()))->all([
            'page' => $this->params->get('page'),
            'perPage' => $this->params->get('perPage') ?? $this->params->get('per_page'),
            'limit' => $this->params->get('limit'),
            'search' => $this->params->get('search'),
            'from' => $this->params->get('from'),
            'until' => $this->params->get('until'),
            'orderBy' => $this->params->get('orderBy') ?? $this->params->get('order_by'),
            'orderDirection' => $this->params->get('orderDirection') ?? $this->params->get('order_direction'),
        ]);

        return [
            'galleries' => collect(data_get($result, 'data', []))->map(function ($gallery) {
                $gallery['cover_image'] = $this->augmentFairuAsset(data_get($gallery, 'cover_image'));

                return $gallery;
            })->all(),
            'paginate' => data_get($result, 'paginatorInfo'),
            'total' => data_get($result, 'paginatorInfo.total', 0),
        ];
    }

    /**
     * The {{ fairu:channel }} tag — one show, with its episodes.
     *
     * Addressed by `id` or by `slug`; a slug reads better in a route and is
     * unique inside the workspace, which is all a site needs.
     *
     * What comes back is what a visitor may see. Pass `preview="true"` for the
     * workspace's own view — drafts and episodes that are not out yet — which is
     * for a live preview and not for a public template.
     *
     * @return array|null
     */
    public function channel()
    {
        $channels = new FairuChannels($this->getConnectionName());

        $options = [
            'preview' => filter_var($this->params->get('preview', false), FILTER_VALIDATE_BOOLEAN),
            'seasons' => $this->params->get('seasons', false),
            'episodes' => $this->params->get('episodes', true),
            'embedWidth' => $this->params->get('embed_width') ?? $this->params->get('embedWidth'),
        ];

        $slug = $this->params->get('slug');
        $id = (string) $this->params->get('id');

        $channel = $slug
            ? $channels->findBySlug($slug, $options)
            : (Str::isUuid($id) ? $channels->find($id, $options) : null);

        if (! is_array($channel)) {
            return null;
        }

        $channel['cover_image'] = $this->augmentFairuAsset(data_get($channel, 'cover_image'));
        $channel['feed_url'] = $channels->feedUrl(data_get($channel, 'id'));
        $channel['is_audio'] = data_get($channel, 'kind') === 'audio';
        $channel['is_video'] = data_get($channel, 'kind') === 'video';

        $channel['episodes'] = $this->augmentEpisodes(data_get($channel, 'episodes'));

        $channel['seasons'] = collect(data_get($channel, 'seasons') ?? [])->map(function ($season) {
            $season['episodes'] = $this->augmentEpisodes(data_get($season, 'episodes'));

            return $season;
        })->all();

        /*
         * An episode page addresses one episode of a channel, and the API has no
         * query for an episode on its own — it belongs to the show. Picking it
         * out here saves every template writing the same loop.
         */
        if ($episodeId = $this->params->get('episode')) {
            $channel['episode'] = collect($channel['episodes'])
                ->first(fn ($episode) => data_get($episode, 'id') === $episodeId);
        }

        return $channel;
    }

    /**
     * The {{ fairu:episode }} tag — one episode of a show.
     *
     * Fairu has no query for an episode on its own, so this is `{{ fairu:channel }}`
     * with the loop already done: the show is fetched (and cached) once and the
     * episode picked out of it. The show stays reachable as `channel`, because a
     * page about an episode nearly always names the show it belongs to.
     *
     * Takes the pair the `fairu_episode` fieldtype stores:
     *
     *     {{ fairu:episode :id="my_episode_field" }}
     *
     * or the two ids by hand, which is what a route with a slug in it needs:
     *
     *     {{ fairu:episode channel="ID" episode="ID" }}
     *
     * With no episode given, the first episode the show hands over is used — an
     * episode page linked to a show alone still has something to render.
     *
     * @return array|null
     */
    public function episode()
    {
        [$channelId, $episodeId] = $this->episodeIds();

        if (! Str::isUuid((string) $channelId)) {
            return null;
        }

        $channels = new FairuChannels($this->getConnectionName());

        $channel = $channels->find($channelId, [
            'preview' => filter_var($this->params->get('preview', false), FILTER_VALIDATE_BOOLEAN),
            'episodes' => true,
            'seasons' => false,
            'embedWidth' => $this->params->get('embed_width') ?? $this->params->get('embedWidth'),
        ]);

        if (! is_array($channel)) {
            return null;
        }

        $episodes = $this->augmentEpisodes(data_get($channel, 'episodes'));

        $episode = $episodeId
            ? collect($episodes)->first(fn ($candidate) => data_get($candidate, 'id') === $episodeId)
            : Arr::first($episodes);

        if (! is_array($episode)) {
            return null;
        }

        $episode['channel'] = [
            'id' => data_get($channel, 'id'),
            'name' => data_get($channel, 'name'),
            'slug' => data_get($channel, 'slug'),
            'kind' => data_get($channel, 'kind'),
            'is_audio' => data_get($channel, 'kind') === 'audio',
            'is_video' => data_get($channel, 'kind') === 'video',
            'description' => data_get($channel, 'description'),
            'cover_image' => $this->augmentFairuAsset(data_get($channel, 'cover_image')),
            'feed_url' => $channels->feedUrl(data_get($channel, 'id')),
        ];

        return $episode;
    }

    /**
     * The two ids `{{ fairu:episode }}` needs, from whichever of its shapes the
     * template used.
     *
     * @return array{0: mixed, 1: mixed}
     */
    protected function episodeIds(): array
    {
        $value = $this->params->get('id');

        $channelId = $this->params->get('channel');
        $episodeId = $this->params->get('episode');

        if (is_array($value)) {
            $channelId ??= Arr::get($value, 'channel');
            $episodeId ??= Arr::get($value, 'episode');
        } elseif (is_string($value) && $value !== '') {
            // A bare id is the channel: the episode is what may be left out.
            $channelId ??= $value;
        }

        return [$channelId, $episodeId];
    }

    /**
     * The {{ fairu:channels }} tag — the shows of this workspace.
     *
     * @return array
     */
    public function channels()
    {
        $channels = new FairuChannels($this->getConnectionName());

        $result = $channels->all([
            'preview' => filter_var($this->params->get('preview', false), FILTER_VALIDATE_BOOLEAN),
            'page' => $this->params->get('page'),
            'perPage' => $this->params->get('perPage') ?? $this->params->get('per_page'),
            'limit' => $this->params->get('limit'),
            'search' => $this->params->get('search'),
        ]);

        return [
            'channels' => collect(data_get($result, 'data', []))->map(function ($channel) use ($channels) {
                $channel['cover_image'] = $this->augmentFairuAsset(data_get($channel, 'cover_image'));
                $channel['feed_url'] = $channels->feedUrl(data_get($channel, 'id'));
                $channel['is_audio'] = data_get($channel, 'kind') === 'audio';
                $channel['is_video'] = data_get($channel, 'kind') === 'video';

                return $channel;
            })->all(),
            'paginate' => data_get($result, 'paginatorInfo'),
            'total' => data_get($result, 'paginatorInfo.total', 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function augmentEpisodes($episodes): array
    {
        return collect($episodes ?? [])->map(function ($episode) {
            $episode['asset'] = $this->augmentFairuAsset(data_get($episode, 'asset'));
            $episode['url'] = data_get($episode, 'asset.url');
            $episode['duration'] = data_get($episode, 'asset.duration');
            $episode['duration_for_humans'] = $this->durationForHumans(data_get($episode, 'asset.duration'));
            $episode['embed_url'] = data_get($episode, 'embed.url');
            $episode['embed_html'] = data_get($episode, 'embed.html');
            $episode['embed_iframe'] = data_get($episode, 'embed.iframe');

            return $episode;
        })->all();
    }

    /**
     * Give an asset from GraphQL the same shape the other tags hand to a
     * template: a URL built from this tag's transform parameters, a srcset when
     * `sources` asks for one, and the focal point as something CSS can use.
     *
     * @return array<string, mixed>|null
     */
    protected function augmentFairuAsset($asset): ?array
    {
        if (! is_array($asset) || blank(data_get($asset, 'id'))) {
            return null;
        }

        $mime = (string) data_get($asset, 'mime');

        $asset['is_image'] = Str::startsWith($mime, 'image/');
        $asset['is_video'] = Str::startsWith($mime, 'video/');
        $asset['is_audio'] = Str::startsWith($mime, 'audio/');

        $asset['url'] = $this->getUrl(
            id: data_get($asset, 'id'),
            filename: data_get($asset, 'name') ?? 'file',
            focalPoint: $this->params->get('focal_point') ?? data_get($asset, 'focal_point'),
            fit: $this->params->get('fit'),
            appendQuery: $asset['is_image']
                || $this->params->get('width')
                || $this->params->get('height')
                || $this->params->get('sources')
                || $this->params->get('timestamp'),
        );

        $sources = $this->getSources($asset, $this->params->get('sources'), null, $this->params->get('ratio'));

        if (! empty($sources)) {
            $asset['srcset'] = implode(', ', $sources);
        }

        $asset['focus_css'] = $this->formatFocalPoint(
            $this->params->get('focal_point') ?? data_get($asset, 'focal_point')
        );

        return $asset;
    }

    /**
     * `1830.5` seconds is what the API answers with and `30:30` is what goes
     * next to an episode in a list. Hours only appear once there are any.
     */
    protected function durationForHumans($seconds): ?string
    {
        if (! is_numeric($seconds) || $seconds <= 0) {
            return null;
        }

        $seconds = (int) round((float) $seconds);

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }
}
