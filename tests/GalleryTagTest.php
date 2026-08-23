<?php

use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use Illuminate\Support\Facades\Http;
use Sushidev\Fairu\Services\FairuCache;
use Sushidev\Fairu\Tags\FairuAssetTags;

const GALLERY_ID = '33333333-3333-3333-3333-333333333333';
const GALLERY_IMAGE_ID = '44444444-4444-4444-4444-444444444444';

beforeEach(function () {
    // Every read is cached under the addon's namespace, and the cache survives
    // between tests in this process — a second test would otherwise assert
    // against the first one's fake.
    FairuCache::flush();

    config()->set('statamic.fairu.connections.default.tenant', '55555555-5555-5555-5555-555555555555');
});

function galleryPayload(array $overrides = []): array
{
    return ['data' => ['fairuGallery' => array_merge([
        'id' => GALLERY_ID,
        'name' => 'Sommerfest',
        'description' => 'A warm evening.',
        'date' => '2026-07-18',
        'location' => 'Graz',
        'copyright_text' => '© Someone',
        'copyrights' => [['id' => '1', 'name' => 'Someone']],
        'cover_image' => null,
        'items' => [[
            'id' => GALLERY_IMAGE_ID,
            'name' => 'hero.jpg',
            'alt' => 'A crowd',
            'caption' => null,
            'mime' => 'image/jpeg',
            'width' => 4000,
            'height' => 3000,
            'focal_point' => '40-30-1',
            'blurhash' => null,
            'duration' => null,
        ]],
    ], $overrides)]];
}

function galleryTag(array $params, string $method = 'gallery')
{
    $tag = new FairuAssetTags();
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag->{$method}();
}

/** The body of the last GraphQL request that was sent. */
function lastGraphqlQuery(): string
{
    $query = null;

    Http::assertSent(function ($request) use (&$query) {
        if (str_contains($request->url(), '/graphql')) {
            $query = data_get($request->data(), 'query');
        }

        return true;
    });

    return (string) $query;
}

/**
 * GraphQL refuses a document that declares a variable it never uses, and the
 * documents here are assembled from parts — the field, the selection and the
 * declaration list are chosen separately, so it is exactly the mistake this
 * code can make.
 */
function expectValidDocument(string $query): void
{
    $ast = Parser::parse($query);

    $declared = [];
    $occurrences = [];

    Visitor::visit($ast, [
        NodeKind::VARIABLE_DEFINITION => function ($node) use (&$declared) {
            $declared[] = $node->variable->name->value;
        },
        NodeKind::VARIABLE => function ($node) use (&$occurrences) {
            $name = $node->name->value;
            $occurrences[$name] = ($occurrences[$name] ?? 0) + 1;
        },
    ]);

    foreach ($declared as $name) {
        // Its own definition is one occurrence, so a variable that is actually
        // used appears at least twice.
        expect($occurrences[$name] ?? 0)
            ->toBeGreaterThan(1, "Variable \${$name} is declared but never used");
    }
}

it('renders a gallery with proxy urls for its items', function () {
    Http::fake(['fairu.app/graphql' => Http::response(galleryPayload())]);

    $gallery = galleryTag(['id' => GALLERY_ID, 'width' => 1200]);

    expect($gallery['name'])->toBe('Sommerfest')
        ->and($gallery['location'])->toBe('Graz')
        ->and($gallery['total_items'])->toBe(1);

    $item = $gallery['items'][0];

    expect($item['url'])->toContain(GALLERY_IMAGE_ID.'/hero.jpg')
        ->and($item['url'])->toContain('width=1200')
        ->and($item['is_image'])->toBeTrue()
        ->and($item['focus_css'])->toBe('40% 30%');
});

it('sends a valid document for a gallery', function () {
    Http::fake(['fairu.app/graphql' => Http::response(galleryPayload())]);

    galleryTag(['id' => GALLERY_ID]);

    $query = lastGraphqlQuery();

    expect($query)->toContain('fairuGallery');
    expectValidDocument($query);
});

it('builds a srcset from sources, exactly as the image tags do', function () {
    Http::fake(['fairu.app/graphql' => Http::response(galleryPayload())]);

    $gallery = galleryTag(['id' => GALLERY_ID, 'sources' => '320,320w;800,1200w']);

    expect($gallery['items'][0]['srcset'])->toContain('width=320')
        ->and($gallery['items'][0]['srcset'])->toContain(' 320w,')
        ->and($gallery['items'][0]['srcset'])->toContain('width=800')
        ->and($gallery['items'][0]['srcset'])->toContain(' 1200w');
});

it('switches to the paginated items when a page is asked for', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuGallery' => [
        'id' => GALLERY_ID,
        'name' => 'Sommerfest',
        'itemsPaginated' => [
            'data' => [[
                'id' => GALLERY_IMAGE_ID,
                'name' => 'hero.jpg',
                'mime' => 'image/jpeg',
                'focal_point' => null,
            ]],
            'paginatorInfo' => ['total' => 240, 'currentPage' => 2, 'lastPage' => 8, 'hasMorePages' => true],
        ],
    ]]])]);

    $gallery = galleryTag(['id' => GALLERY_ID, 'page' => 2, 'perPage' => 30]);

    expect($gallery['items'])->toHaveCount(1)
        // The paginated list is flattened into `items` so a template does not
        // have to know which parameter it happened to pass.
        ->and($gallery['total_items'])->toBe(240)
        ->and($gallery['paginate']['hasMorePages'])->toBeTrue()
        ->and($gallery)->not->toHaveKey('itemsPaginated');

    expectValidDocument(lastGraphqlQuery());
});

it('renders nothing rather than failing when Fairu is unreachable', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['errors' => [['message' => 'Unauthenticated.']]], 200)]);

    expect(galleryTag(['id' => GALLERY_ID]))->toBeNull();
});

it('does not cache a failure', function () {
    $responses = [
        Http::response(['errors' => [['message' => 'Service Unavailable']]], 200),
        Http::response(galleryPayload()),
    ];

    Http::fake(['fairu.app/graphql' => Http::sequence($responses)]);

    expect(galleryTag(['id' => GALLERY_ID]))->toBeNull();

    // A minute of Fairu being unreachable must not leave the site rendering an
    // empty gallery for the rest of the TTL.
    expect(galleryTag(['id' => GALLERY_ID])['name'])->toBe('Sommerfest');
});

it('ignores an id that is not a gallery id', function () {
    Http::fake(['fairu.app/graphql' => Http::response(galleryPayload())]);

    // `resolveIds` would hash this into a file uuid and ask for a gallery that
    // cannot exist. A gallery id is a gallery id or it is nothing.
    expect(galleryTag(['id' => 'assets/hero.jpg']))->toBeNull();

    Http::assertNothingSent();
});

it('lists the galleries of the workspace', function () {
    Http::fake(['fairu.app/graphql' => Http::response(['data' => ['fairuGalleries' => [
        'data' => [
            ['id' => GALLERY_ID, 'name' => 'Sommerfest', 'date' => '2026-07-18', 'cover_image' => [
                'id' => GALLERY_IMAGE_ID,
                'name' => 'hero.jpg',
                'mime' => 'image/jpeg',
                'focal_point' => null,
            ]],
        ],
        'paginatorInfo' => ['total' => 1, 'currentPage' => 1, 'lastPage' => 1, 'hasMorePages' => false],
    ]]])]);

    $result = galleryTag(['perPage' => 12], 'galleries');

    expect($result['total'])->toBe(1)
        ->and($result['galleries'][0]['name'])->toBe('Sommerfest')
        ->and($result['galleries'][0]['cover_image']['url'])->toContain(GALLERY_IMAGE_ID.'/hero.jpg');

    expectValidDocument(lastGraphqlQuery());
});

it('does not ask for galleries without a tenant to ask about', function () {
    config()->set('statamic.fairu.connections.default.tenant', null);

    Http::fake();

    expect(galleryTag([], 'galleries')['galleries'])->toBe([]);

    Http::assertNothingSent();
});
