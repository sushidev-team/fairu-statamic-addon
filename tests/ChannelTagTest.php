<?php

use Illuminate\Support\Facades\Http;
use Sushidev\Fairu\Services\FairuCache;
use Sushidev\Fairu\Tags\FairuAssetTags;

const CHANNEL_ID = '66666666-6666-6666-6666-666666666666';
const EPISODE_ID = '77777777-7777-7777-7777-777777777777';
const EPISODE_ASSET_ID = '88888888-8888-8888-8888-888888888888';

beforeEach(function () {
    FairuCache::flush();

    config()->set('statamic.fairu.connections.default.tenant', '55555555-5555-5555-5555-555555555555');
});

function channelBody(array $overrides = []): array
{
    return array_merge([
        'id' => CHANNEL_ID,
        'name' => 'Die Werkstatt',
        'slug' => 'die-werkstatt',
        'kind' => 'audio',
        'description' => 'Two people and a lathe.',
        'author' => 'Sushi Dev',
        'explicit' => false,
        'cover_image' => [
            'id' => EPISODE_ASSET_ID,
            'name' => 'cover.jpg',
            'mime' => 'image/jpeg',
            'focal_point' => null,
        ],
        'embed' => ['url' => 'https://fairu.app/embed/channel/'.CHANNEL_ID, 'html' => '<div></div>', 'iframe' => '<iframe></iframe>'],
        'episodes' => [[
            'id' => EPISODE_ID,
            'number' => 14,
            'title' => 'Der Hobel',
            'description' => 'On planes.',
            'show_notes' => '<p>Links</p>',
            'published_at' => '2026-07-18T06:00:00+00:00',
            'episode_type' => 'full',
            'explicit' => false,
            'orientation' => null,
            'aspect_ratio' => null,
            'embed' => ['url' => 'https://fairu.app/embed/'.EPISODE_ASSET_ID, 'html' => '<div></div>', 'iframe' => '<iframe></iframe>'],
            'asset' => [
                'id' => EPISODE_ASSET_ID,
                'name' => 'episode-14.mp3',
                'mime' => 'audio/mpeg',
                'focal_point' => null,
                'duration' => 1830.5,
            ],
        ]],
    ], $overrides);
}

function channelTag(array $params, string $method = 'channel')
{
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->{$method}();
}

/**
 * The visitor's view is the default, and this is the test that matters most in
 * this file.
 *
 * The addon authenticates with a workspace key, so the authenticated query would
 * hand a public template the drafts and the episodes whose release window has
 * not opened — and a template looping over `episodes` has no way to tell.
 */
it('asks for the public channel unless preview is asked for', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => channelBody()]])]);

    $channel = channelTag(['id' => CHANNEL_ID]);

    expect($channel['name'])->toBe('Die Werkstatt');

    $query = lastGraphqlQuery();

    expect($query)->toContain('fairuPublicVideoChannel')
        ->and($query)->not->toContain('fairuVideoChannel(');

    expectValidDocument($query);
});

it('asks for the workspace view when preview is asked for', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuVideoChannel' => channelBody()]])]);

    $channel = channelTag(['id' => CHANNEL_ID, 'preview' => 'true']);

    expect($channel['name'])->toBe('Die Werkstatt');
    expect(lastGraphqlQuery())->toContain('fairuVideoChannel(id:');

    expectValidDocument(lastGraphqlQuery());
});

it('gives an episode everything a template renders it with', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => channelBody()]])]);

    $channel = channelTag(['id' => CHANNEL_ID]);
    $episode = $channel['episodes'][0];

    expect($episode['title'])->toBe('Der Hobel')
        ->and($episode['duration'])->toBe(1830.5)
        ->and($episode['duration_for_humans'])->toBe('30:31')
        ->and($episode['embed_url'])->toContain('/embed/')
        ->and($episode['url'])->toContain(EPISODE_ASSET_ID.'/episode-14.mp3')
        ->and($episode['asset']['is_audio'])->toBeTrue();

    // An audio file must not be routed through the image proxy's transforms.
    expect($episode['url'])->not->toContain('quality=90');
});

it('carries the feed address and what kind of show it is', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => channelBody()]])]);

    $channel = channelTag(['id' => CHANNEL_ID]);

    expect($channel['feed_url'])->toBe('https://fairu.app/channels/'.CHANNEL_ID.'/feed.xml')
        ->and($channel['is_audio'])->toBeTrue()
        ->and($channel['is_video'])->toBeFalse();
});

it('picks out the one episode an episode page is about', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => channelBody()]])]);

    $channel = channelTag(['id' => CHANNEL_ID, 'episode' => EPISODE_ID]);

    expect($channel['episode']['title'])->toBe('Der Hobel');
});

it('names the workspace when addressing a channel by slug', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannelBySlug' => channelBody()]])]);

    $channel = channelTag(['slug' => 'die-werkstatt']);

    expect($channel['name'])->toBe('Die Werkstatt');

    $query = lastGraphqlQuery();

    // A slug is unique inside a workspace and nowhere else.
    expect($query)->toContain('fairuPublicVideoChannelBySlug')
        ->and($query)->toContain('tenant: $tenant');

    expectValidDocument($query);
});

it('asks for seasons only when a template wants them nested', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => channelBody()]])]);

    channelTag(['id' => CHANNEL_ID]);

    expect(lastGraphqlQuery())->not->toContain('seasons {');

    FairuCache::flush();

    channelTag(['id' => CHANNEL_ID, 'seasons' => 'true']);

    expect(lastGraphqlQuery())->toContain('seasons {');
});

it('lists the published channels of the workspace', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannels' => [
        'data' => [channelBody(['episodes' => null, 'embed' => null])],
        'paginatorInfo' => ['total' => 1, 'currentPage' => 1, 'lastPage' => 1, 'hasMorePages' => false],
    ]]])]);

    $result = channelTag(['perPage' => 10], 'channels');

    expect($result['total'])->toBe(1)
        ->and($result['channels'][0]['name'])->toBe('Die Werkstatt')
        ->and($result['channels'][0]['feed_url'])->toContain('/feed.xml')
        ->and($result['channels'][0]['cover_image']['url'])->toContain(EPISODE_ASSET_ID.'/cover.jpg');

    expectValidDocument(lastGraphqlQuery());
});

it('renders nothing rather than failing when the channel is gone', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => null]])]);

    expect(channelTag(['id' => CHANNEL_ID]))->toBeNull();
});

it('needs an id or a slug', function () {
    Http::fake();

    expect(channelTag([]))->toBeNull();

    Http::assertNothingSent();
});
