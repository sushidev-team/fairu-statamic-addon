<?php

namespace Sushidev\Fairu\Fieldtypes;

use Illuminate\Contracts\Validation\Rule;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu;

class MimetypesRule implements Rule
{
    protected $parameters;

    public function __construct($parameters)
    {
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

            $mimeType = data_get($file, 'mime');

            if (! $mimeType) {
                return false;
            }

            return in_array($mimeType, $this->parameters)
                || in_array(explode('/', $mimeType)[0] . '/*', $this->parameters);
        });
    }

    public function message()
    {
        return str_replace(
            ':values',
            implode(', ', $this->parameters),
            __((Statamic::isCpRoute() ? 'statamic::' : '') . 'validation.mimetypes')
        );
    }
}
