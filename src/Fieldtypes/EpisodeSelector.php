<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Pick one episode of a show.
 *
 * Stores the pair, not the episode alone:
 *
 *     ['channel' => '…', 'episode' => '…']
 *
 * because Fairu has no query for an episode on its own — an episode belongs to
 * its channel and is only addressable through it. Leaving the episode empty is
 * allowed and means the show as a whole, which is what an episode page falls
 * back to before its first episode exists.
 *
 * Rendering belongs to `{{ fairu:episode }}`, for the same reason it does for
 * galleries and channels: the tag caches, an augmented field would fetch on
 * every render.
 *
 *     {{ fairu:episode :id="my_episode_field" }}
 */
class EpisodeSelector extends Fieldtype
{
    protected $icon = 'video';

    public $categories = ['media', 'relationship'];

    public static $title = 'Fairu Episode';

    protected $keywords = ['fairu', 'episode', 'channel', 'podcast', 'video', 'audio', 'show'];

    public static function handle(): string
    {
        return 'fairu_episode';
    }

    public function icon()
    {
        return file_get_contents(__DIR__ . '/../../resources/svg/fairu-favicon.svg');
    }

    protected function configFieldItems(): array
    {
        return [
            'connection' => [
                'display' => __('fairu::fieldtype.connection'),
                'instructions' => __('fairu::fieldtype.connection_instructions'),
                'type' => 'text',
                'default' => 'default',
                'width' => 50,
            ],
        ];
    }

    public function preload()
    {
        return [
            'channels_endpoint' => route('fairu.channels'),
            'episodes_endpoint' => route('fairu.episodes'),
            'connection' => $this->config('connection', 'default'),
        ];
    }

    /**
     * A value saved before the field held a pair — or typed by hand — is read as
     * the channel, so that widening an existing `fairu_channel` field to this one
     * does not drop what is already in the content.
     */
    public function preProcess($value)
    {
        if (is_string($value) && $value !== '') {
            return ['channel' => $value, 'episode' => null];
        }

        return $value;
    }

    public function augment($value)
    {
        return $value;
    }
}
