<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Pick a gallery.
 *
 * Stores the gallery id and nothing else. Rendering is the `{{ fairu:gallery }}`
 * tag's job, because that is where the transform parameters live — a field that
 * augmented itself into a list of images would have to guess at the widths, and
 * every entry listing that touched it would pay for a gallery it never showed.
 *
 *     {{ fairu:gallery :id="my_gallery_field" width="1200" }}
 */
class GallerySelector extends Fieldtype
{
    protected $icon = 'assets';

    public $categories = ['media', 'relationship'];

    public static $title = 'Fairu Gallery';

    protected $keywords = ['fairu', 'gallery', 'galleries', 'album', 'photos'];

    public static function handle(): string
    {
        return 'fairu_gallery';
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
            'endpoint' => route('fairu.galleries'),
            'connection' => $this->config('connection', 'default'),
        ];
    }

    public function augment($value)
    {
        return $value;
    }
}
