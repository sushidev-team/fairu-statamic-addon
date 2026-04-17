<?php

namespace Sushidev\Fairu\Services;

/**
 * Per-request registry that collects fairu tag placeholders during Antlers
 * rendering so that the CoalesceFairuMeta middleware can resolve every needed
 * asset in a single /api/files/meta call after the view finishes rendering.
 */
class FairuMetaBag
{
    /** Prefix used in the emitted placeholder token. Must survive HTML attribute contexts. */
    public const TOKEN_PREFIX = '__FAIRU_';

    public const TOKEN_SUFFIX = '__';

    /** @var array<string, array{type:string, id:?string, params:array, connection:string}> */
    protected array $entries = [];

    /** @var array<string, array<string, mixed>> */
    protected array $resolved = [];

    protected bool $active = false;

    public function enable(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Queue a placeholder for deferred rendering. Returns the token that should
     * be emitted into the response body in place of the final HTML/URL.
     */
    public function queue(string $type, ?string $id, array $params, string $connection = 'default'): string
    {
        $handle = bin2hex(random_bytes(8));

        $this->entries[$handle] = [
            'type' => $type,
            'id' => $id,
            'params' => $params,
            'connection' => $connection,
        ];

        return $this->token($handle);
    }

    public function token(string $handle): string
    {
        return self::TOKEN_PREFIX.$handle.self::TOKEN_SUFFIX;
    }

    /** @return array<string, array{type:string, id:?string, params:array, connection:string}> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function hasEntries(): bool
    {
        return ! empty($this->entries);
    }

    /**
     * Unique ids grouped by connection for batched meta fetching.
     *
     * @return array<string, array<int, string>>
     */
    public function pendingIdsByConnection(): array
    {
        $grouped = [];

        foreach ($this->entries as $entry) {
            if (empty($entry['id'])) {
                continue;
            }

            $grouped[$entry['connection']][$entry['id']] = true;
        }

        return array_map(static fn ($ids) => array_keys($ids), $grouped);
    }

    public function setResolved(string $id, array $meta): void
    {
        $this->resolved[$id] = $meta;
    }

    public function meta(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return $this->resolved[$id] ?? null;
    }

    public function reset(): void
    {
        $this->entries = [];
        $this->resolved = [];
        $this->active = false;
    }
}
