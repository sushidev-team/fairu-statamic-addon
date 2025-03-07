<?php

use Illuminate\Support\Facades\Route;
use SushidevTeam\Fairu\Http\Controllers\AssetController;

Route::get('/assets/browse/{container}', [AssetController::class, 'index'])->name('assets');
