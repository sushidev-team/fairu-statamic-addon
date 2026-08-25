<?php

use Illuminate\Support\Facades\Http;
use Sushidev\Fairu\Services\FairuCache;
use Sushidev\Fairu\Tags\FairuAssetTags;

const EP_CHANNEL_ID = '11111111-1111-1111-1111-111111111111';
const EP_FIRST_ID = '22222222-2222-2222-2222-222222222222';
const EP_SECOND_ID = '33333333-3333-3333-3333-333333333333';
const EP_ASSET_ID = '44444444-4444-4444-4444-444444444444';

beforeEach(function () {
    FairuCache::flush();

    config()->set('statamic.fairu.connections.default.tenant', '55555555-5555-5555-5555-555555555555');
});

function episodeChannelBody(): array
{
    $episode = fn (string $id, int $number, string $title) => [
        'id' => $id,
        'number' => $number,
        'title' => $title,
        'description' => 'On planes.',
        'show_notes' => '<p>Links</p>',
        'published_at' => '2026-07-18T06:00:00+00:00',
        'episode_type' => 'full',
        'explicit' => false,
        'orientation' => null,
        'aspect_ratio' => null,
        'embed' => ['url' => 'https://fairu.app/embed/'.$id, 'html' => '<div></div>', 'iframe' => '<iframe></iframe>'],
        'asset' => [
            'id' => EP_ASSET_ID,
            'name' => 'episode.mp3',
            'mime' => 'audio/mpeg',
            'focal_point' => null,
            'duration' => 1830.5,
        ],
    ];

    return [
        'id' => EP_CHANNEL_ID,
        'name' => 'Die Werkstatt',
        'slug' => 'die-werkstatt',
        'kind' => 'audio',
        'description' => 'Two people and a lathe.',
        'cover_image' => [
            'id' => EP_ASSET_ID,
            'name' => 'cover.jpg',
            'mime' => 'image/jpeg',
            'focal_point' => null,
        ],
        'embed' => ['url' => 'https://fairu.app/embed/channel', 'html' => '<div></div>', 'iframe' => '<iframe></iframe>'],
        'episodes' => [
            $episode(EP_FIRST_ID, 14, 'Der Hobel'),
            $episode(EP_SECOND_ID, 13, 'Die Ziehklinge'),
        ],
    ];
}

function episodeTag(array $params)
{
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->episode();
}

function fakeEpisodeChannel(): void
{
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => episodeChannelBody()]])]);
}

/**
 * The shape the `fairu_episode` fieldtype stores, which is what a template hands
 * straight to the tag.
 */
it('takes the pair the fieldtype stores', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['id' => ['channel' => EP_CHANNEL_ID, 'episode' => EP_SECOND_ID]]);

    expect($episode['title'])->toBe('Die Ziehklinge')
        ->and($episode['number'])->toBe(13);
});

it('takes the two ids by hand', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['channel' => EP_CHANNEL_ID, 'episode' => EP_SECOND_ID]);

    expect($episode['title'])->toBe('Die Ziehklinge');
});

/**
 * A page linked to a show alone still has to render something, so the episode is
 * the part that may be left out.
 */
it('falls back to the first episode when none is named', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['id' => ['channel' => EP_CHANNEL_ID, 'episode' => null]]);

    expect($episode['title'])->toBe('Der Hobel');
});

it('reads a bare id as the channel', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['id' => EP_CHANNEL_ID]);

    expect($episode['title'])->toBe('Der Hobel');
});

/**
 * A page about an episode nearly always names the show it came from, so the show
 * stays reachable instead of costing a second tag.
 */
it('leaves the show reachable beside the episode', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['id' => EP_CHANNEL_ID]);

    expect($episode['channel']['name'])->toBe('Die Werkstatt')
        ->and($episode['channel']['is_audio'])->toBeTrue()
        ->and($episode['channel']['feed_url'])->toBe('https://fairu.app/channels/'.EP_CHANNEL_ID.'/feed.xml')
        ->and($episode['channel']['cover_image']['url'])->toContain(EP_ASSET_ID.'/cover.jpg');
});

it('gives the episode what a template renders it with', function () {
    fakeEpisodeChannel();

    $episode = episodeTag(['id' => EP_CHANNEL_ID]);

    expect($episode['duration_for_humans'])->toBe('30:31')
        ->and($episode['embed_html'])->toBe('<div></div>')
        ->and($episode['embed_iframe'])->toBe('<iframe></iframe>')
        ->and($episode['url'])->toContain(EP_ASSET_ID.'/episode.mp3');

    // An audio file must not be routed through the image proxy's transforms.
    expect($episode['url'])->not->toContain('quality=90');
});

it('asks for the public channel unless preview is asked for', function () {
    fakeEpisodeChannel();

    episodeTag(['id' => EP_CHANNEL_ID]);

    $query = lastGraphqlQuery();

    expect($query)->toContain('fairuPublicVideoChannel')
        ->and($query)->not->toContain('fairuVideoChannel(');

    expectValidDocument($query);
});

it('renders nothing rather than failing when the episode is not in the show', function () {
    fakeEpisodeChannel();

    expect(episodeTag(['channel' => EP_CHANNEL_ID, 'episode' => 'not-in-this-show']))->toBeNull();
});

it('renders nothing when the show is gone', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuPublicVideoChannel' => null]])]);

    expect(episodeTag(['id' => EP_CHANNEL_ID]))->toBeNull();
});

/**
 * A gallery id, an old asset path, an empty field — none of them are a channel,
 * and none of them are worth a round trip.
 */
it('needs a channel id that is a uuid', function () {
    Http::fake();

    expect(episodeTag([]))->toBeNull()
        ->and(episodeTag(['id' => 'nonsense']))->toBeNull()
        ->and(episodeTag(['id' => ['channel' => null, 'episode' => EP_FIRST_ID]]))->toBeNull();

    Http::assertNothingSent();
});
