<?php

namespace Sushidev\Fairu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sushidev\Fairu\Services\Fairu;
use Sushidev\Fairu\Services\FairuAssetRenderer;
use Sushidev\Fairu\Services\FairuMetaBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Collects every fairu tag placeholder emitted during Antlers rendering,
 * resolves them all with a single /api/files/meta call, and rewrites the
 * response body in place. Must run inside Statamic's static-cache middleware
 * so the cached HTML contains the final output rather than placeholders.
 */
class CoalesceFairuMeta
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('statamic.fairu.coalesce_meta', true)) {
            return $next($request);
        }

        /** @var FairuMetaBag $bag */
        $bag = app(FairuMetaBag::class);
        $bag->reset();
        $bag->enable();

        try {
            $response = $next($request);

            if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
                return $response;
            }

            $contentType = (string) $response->headers->get('Content-Type', '');
            if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
                return $response;
            }

            $body = $response->getContent();
            if (! is_string($body) || $body === '') {
                return $response;
            }

            // Nothing queued and nothing left over from a cached fragment.
            if (! $bag->hasEntries() && ! str_contains($body, FairuMetaBag::TOKEN_PREFIX)) {
                return $response;
            }

            foreach ($bag->pendingIdsByConnection() as $connection => $ids) {
                try {
                    $meta = app()->make(Fairu::class, ['connection' => $connection])->getFilesMeta($ids);

                    foreach ((array) $meta as $item) {
                        $id = data_get($item, 'id');
                        if (is_string($id) && $id !== '') {
                            $bag->setResolved($id, (array) $item, $connection);
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('Fairu: coalesced meta fetch failed for connection '.$connection.': '.$e->getMessage());
                }
            }

            // Nested tags in deferred bodies must render immediately.
            $bag->disable();

            /** @var FairuAssetRenderer $renderer */
            $renderer = app(FairuAssetRenderer::class);

            foreach ($bag->entries() as $handle => $entry) {
                $token = $bag->token($handle);
                if (strpos($body, $token) === false) {
                    continue;
                }

                if ($entry['type'] === 'list') {
                    $assets = [];
                    foreach ((array) ($entry['ids'] ?? []) as $id) {
                        $meta = $bag->meta($id, $entry['connection']);
                        $assets[] = $meta ?? ['id' => $id];
                    }

                    $replacement = $renderer->renderList(
                        assets: $assets,
                        params: $entry['params'],
                        body: (string) ($entry['body'] ?? ''),
                        context: (array) ($entry['context'] ?? []),
                        connection: $entry['connection'],
                        language: $entry['language'],
                    );
                } else {
                    $asset = $bag->meta($entry['id'], $entry['connection']);
                    if ($asset === null && $entry['id'] !== null) {
                        $asset = ['id' => $entry['id']];
                    }

                    $replacement = $renderer->render($entry['type'], $entry['params'], $asset, $entry['connection']);
                }

                $body = str_replace($token, $replacement, $body);
            }

            $body = $this->stripStaleTokens($body);

            $response->setContent($body);

            return $response;
        } finally {
            $bag->reset();
        }
    }

    /**
     * Remove placeholders this request cannot resolve.
     *
     * A token only survives to here when the HTML holding it was rendered in an
     * earlier request and replayed from a cache (an Antlers `{{ cache }}` block,
     * static caching, a CDN). Its handle died with the bag that issued it, so
     * the asset is unrecoverable — drop the token rather than let the raw
     * `__FAIRU_…__` text render as visible page content.
     */
    protected function stripStaleTokens(string $body): string
    {
        $stale = [];

        $cleaned = preg_replace_callback(
            FairuMetaBag::TOKEN_PATTERN,
            function (array $matches) use (&$stale) {
                $stale[] = $matches[0];

                return '';
            },
            $body
        );

        if ($stale !== []) {
            Log::warning(sprintf(
                'Fairu: dropped %d unresolvable placeholder(s) (%s). They were rendered into HTML that was cached in an earlier request — move the fairu tag outside the {{ cache }} block or clear that fragment.',
                count($stale),
                implode(', ', array_slice($stale, 0, 5))
            ));
        }

        return $cleaned;
    }
}
