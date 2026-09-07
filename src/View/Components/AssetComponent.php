<?php

namespace Sushidev\Fairu\View\Components;

use Closure;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Sushidev\Fairu\Tags\FairuAssetTags;

abstract class AssetComponent extends Component
{
    protected const TAG = '';

    public function __construct(public mixed $id = null, public mixed $ids = null, public array $params = []) {}

    public function render(): Closure
    {
        return function (array $data) {
            $params = $this->params;
            $attributes = [];
            $options = ['name', 'alt', 'width', 'height', 'quality', 'sources', 'ratio', 'format', 'fit',
                'focal_point', 'timestamp', 'fetch_meta', 'fetchMeta', 'raw', 'download', 'connection',
                'class', 'sizes', 'loading', 'decoding', 'fetchpriority'];
            foreach ($data['attributes']->getAttributes() as $key => $value) {
                $normalized = str_replace('-', '_', $key);
                if (in_array($normalized, $options, true)) {
                    // Blade has already escaped bound attribute strings.
                    $params[$normalized] = is_string($value)
                        ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                        : $value;
                } else {
                    $attributes[$key] = $value;
                }
            }
            $params['id'] = $this->id ?? ($params['id'] ?? null);
            $params['ids'] = $this->ids ?? ($params['ids'] ?? null);
            $params['_attributes'] = $attributes;
            $params['_escape'] = static::TAG === 'url';
            $tag = new FairuAssetTags();
            $tag->setContext([]);
            $tag->setParameters($params);
            $output = $tag->{static::TAG}();

            // Keep data out of template source: attribute values can contain Blade syntax.
            return view('fairu::components.output', ['output' => new HtmlString($output)]);
        };
    }
}
