<?php

namespace Sushidev\Fairu\Fieldtypes;

use Statamic\Fields\Fieldtype;

class FolderSelector extends Fieldtype
{
    protected $icon = 'folder-open';


    public function preload()
    {
        return ['proxy' => config('fairu.url_proxy'), 'file' => config('fairu.url') . '/files'];
    }

    /**
     * The blank/default value.
     *
     * @return string
     */
    public function defaultValue()
    {
        return '';
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     *
     * @param mixed $data
     * @return mixed
     */
    public function preProcess($data)
    {
        return $data;
    }

    /**
     * Process the data before it gets saved.
     *
     * @param mixed $data
     * @return mixed
     */
    public function process($data)
    {
        return $data;
    }
}
