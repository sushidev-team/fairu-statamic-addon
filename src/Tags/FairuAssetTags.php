<?php

namespace SushidevTeam\Fairu\Tags;

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

    /**
     * The {{ fairu }} tag.
     *
     * @return string
     */
    public function index()
    {
       return Cache::flexible($this->params->get('id'), [30, 120], function(){

            $result = (new Fairu($this->params->get('connection', 'default')))->getFile($this->params->get('id'));

            $set = data_get($result, 'data');

            data_set($set, 'url', data_get($set, 'stream'));

            return $set;

       });
    }

}