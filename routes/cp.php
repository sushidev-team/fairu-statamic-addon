<?php

use Illuminate\Support\Facades\Route;
use Sushidev\Fairu\Http\Controllers\AssetController;

if (config('statamic.fairu.deactivate_old') == true) {
    Route::get('/fairu/browser', [AssetController::class, 'browser'])->name('fairu.browser');
}
