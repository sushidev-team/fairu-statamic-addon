<?php

namespace Sushidev\Fairu\Fieldtypes;

use Illuminate\Contracts\Validation\Rule;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu;

class MaxFilesizeRule implements Rule
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
        $maxKb = $this->parameters[0];

        return collect($ids)->every(function ($id) use ($filesById, $maxKb) {
            $file = $filesById->get($id);

            if (! $file) {
                return false;
            }

            $sizeInKb = data_get($file, 'size') / 1024;

            return $sizeInKb <= $maxKb;
        });
    }

    public function message()
    {
        return str_replace(
            ':max',
            $this->parameters[0],
            __((Statamic::isCpRoute() ? 'statamic::' : '') . 'validation.max.file')
        );
    }
}
