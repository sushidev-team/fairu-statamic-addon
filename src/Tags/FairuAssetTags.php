<?php

namespace SushidevTeam\Fairu\Tags;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Statamic\SeoPro\Cascade;
use Statamic\SeoPro\GetsSectionDefaults;
use Statamic\SeoPro\RendersMetaHtml;
use Statamic\SeoPro\SiteDefaults;
use Statamic\Tags\Tags;
use SushidevTeam\Fairu\Services\Fairu;

class FairuAssetTags extends Tags
{

    protected static $handle = 'fairu';

    protected function getFile(string $id){
        return Cache::flexible('file-'.$id, config('app.debug') ? [0,0]: config('fairu.caching_meta'), function(){
            return (new Fairu($this->params->get('connection', 'default')))->getFile($this->params->get('id'));
        });
    }

    protected function getUrl(string $id, ?string $filename){
        $params = [
            'width' =>$this->params->get('width'),
            'height' => $this->params->get('height'),
            'quality' => $this->params->get('quality', 90),
            'format' => $this->params->get('format'),
            'focal' => $this->params->get('focal_point'),
        ];

        $queryString = http_build_query($params);

        return (Str::endsWith(config('fairu.url_proxy'), "/") ? config('fairu.url_proxy') : config('fairu.url_proxy') . "/") . $id . "/" . ($filename ?? 'file') . '?' . $queryString;

    }

    /**
     * The {{ fairu:url }} tag.
     *
     * @return string
     */
    public function url() {
        return $this->getUrl($this->params->get('id'), $this->params->get('name', 'file'));
    }

    /**
     * The {{ fairu }} tag.
     *
     * @return string
     */
    public function index()
    {
    
      $cacheKey = md5(json_encode($this->params->toArray()));

      return Cache::flexible($cacheKey, config('app.debug') ? [0,0]: config('fairu.caching_meta'), function(){

            $file = $this->getFile($this->params->get('id'));
            $url = $this->getUrl($this->params->get('id'), $this->params->get('name') ?? data_get($file, 'filename'));

            $set = data_get($file, 'data');
            data_set($set, 'url', $url,);

            data_set($set, 'fields', array_keys($set));

            return $set;

       });
    }

}