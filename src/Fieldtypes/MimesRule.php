<?php

namespace Sushidev\Fairu\Fieldtypes;

use Illuminate\Contracts\Validation\Rule;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu;
use Symfony\Component\Mime\MimeTypes;

class MimesRule implements Rule
{
    protected $parameters;

    public function __construct($parameters)
    {
        if (in_array('jpg', $parameters) || in_array('jpeg', $parameters)) {
            $parameters = array_unique(array_merge($parameters, ['jpg', 'jpeg']));
        }

        $this->parameters = $parameters;
    }

    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return true;
        }

        $ids = is_array($value) ? $value : [$value];
        $files = (new Fairu)->getFiles($ids);

        if (! $files) {
            return false;
        }

        $filesById = collect($files)->keyBy('id');

        return collect($ids)->every(function ($id) use ($filesById) {
            $file = $filesById->get($id);

            if (! $file) {
                return false;
            }

            $mime = data_get($file, 'mime');

            if (! $mime) {
                return false;
            }

            $extensions = MimeTypes::getDefault()->getExtensions($mime);

            return ! empty(array_intersect($extensions, $this->parameters));
        });
    }

    public function message()
    {
        return str_replace(
            ':values',
            implode(', ', $this->parameters),
            __((Statamic::isCpRoute() ? 'statamic::' : '') . 'validation.mimes')
        );
    }
}
