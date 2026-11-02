<?php

namespace Sushidev\Fairu\Support;

use Illuminate\Support\Facades\Log;

/**
 * Logs a missing/invalid watermark key at most once per request (per connection and reason),
 * so a page full of watermark="false" images does not flood the log.
 */
final class WatermarkKeyWarning
{
    public static function log(string $connection, string $reason): void
    {
        once(function () use ($connection, $reason): void {
            Log::warning("Fairu: watermark=\"false\" requested but {$reason} for connection \"{$connection}\". Emitting a watermarked URL. Set FAIRU_WATERMARK_KEY (statamic.fairu.connections.{$connection}.watermark_key).");
        });
    }
}
