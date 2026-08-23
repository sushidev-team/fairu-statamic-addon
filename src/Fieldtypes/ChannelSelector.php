<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Pick a channel — a video show or a podcast.
 *
 * Stores the channel id, and rendering belongs to `{{ fairu:channel }}` for the
 * same reason it does for galleries. The picker offers unpublished shows too:
 * the page that will publish one has to be built before it goes live.
 *
 *     {{ fairu:channel :id="my_channel_field" }}
 */
class ChannelSelector extends Fieldtype
{
    protected $icon = 'video';

    public $categories = ['media', 'relationship'];

    public static $title = 'Fairu Channel';

    protected $keywords = ['fairu', 'channel', 'podcast', 'video', 'audio', 'show', 'episodes'];

    public static function handle(): string
    {
        return 'fairu_channel';
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
            'endpoint' => route('fairu.channels'),
            'connection' => $this->config('connection', 'default'),
        ];
    }

    public function augment($value)
    {
        return $value;
    }
}
