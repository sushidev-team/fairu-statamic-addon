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

        $response = $next($request);

        if (! $bag->hasEntries()) {
            return $response;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
            return $response;
        }

        foreach ($bag->pendingIdsByConnection() as $connection => $ids) {
            try {
                $meta = app()->make(Fairu::class, ['connection' => $connection])->getFilesMeta($ids);

                foreach ((array) $meta as $item) {
                    $id = data_get($item, 'id');
                    if (is_string($id) && $id !== '') {
                        $bag->setResolved($id, (array) $item);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Fairu: coalesced meta fetch failed for connection '.$connection.': '.$e->getMessage());
            }
        }

        /** @var FairuAssetRenderer $renderer */
        $renderer = app(FairuAssetRenderer::class);

        $body = $response->getContent();
        if (! is_string($body) || $body === '') {
            return $response;
        }

        foreach ($bag->entries() as $handle => $entry) {
            $token = $bag->token($handle);
            if (strpos($body, $token) === false) {
                continue;
            }

            $asset = $bag->meta($entry['id']);
            if ($asset === null && $entry['id'] !== null) {
                $asset = ['id' => $entry['id']];
            }

            $replacement = $renderer->render($entry['type'], $entry['params'], $asset, $entry['connection']);

            $body = str_replace($token, $replacement, $body);
        }

        $response->setContent($body);

        return $response;
    }
}
