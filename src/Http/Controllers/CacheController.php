<?php

namespace Sushidev\Fairu\Http\Controllers;

class CacheController extends \Statamic\Http\Controllers\CP\Utilities\CacheController
{
    public function index()
    {
        return view('fairu::cache', [
            'stache' => $this->getStacheStats(),
            'cache' => $this->getApplicationCacheStats(),
            'static' => $this->getStaticCacheStats(),
            'images' => $this->getImageCacheStats(),
        ]);
    }
}
