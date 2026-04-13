<?php

namespace Sushidev\Fairu\Fieldtypes;

use Illuminate\Contracts\Validation\Rule;
use Statamic\Statamic;
use Sushidev\Fairu\Services\Fairu;
use Symfony\Component\Mime\MimeTypes;

class ImageRule implements Rule
{
    protected $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'avif'];

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

            return ! empty(array_intersect($extensions, $this->extensions));
        });
    }

    public function message()
    {
        return __((Statamic::isCpRoute() ? 'statamic::' : '') . 'validation.image');
    }
}
