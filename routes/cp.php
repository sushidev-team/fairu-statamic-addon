<?php

use Illuminate\Support\Facades\Route;
use Sushidev\Fairu\Http\Controllers\AssetController;

if (config('statamic.fairu.deactivate_old') == true) {
    Route::get('/assets/browse/{container}', [AssetController::class, 'index'])->name('assets');
}
