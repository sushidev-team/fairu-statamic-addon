<?php

use Illuminate\Support\Facades\Route;
use Sushidev\Fairu\Http\Controllers\AssetController;

Route::name('fairu.')
    ->middleware('auth') // Ensures the user is logged into the CP
    ->group(function () {
        Route::get('/fairu', [AssetController::class, 'base'])->name('base');
        Route::post('/fairu/upload', [AssetController::class, 'upload'])->name('upload');
        Route::post('/fairu/upload-multiple', [AssetController::class, 'uploadMultiple'])->name('upload_multiple');
        Route::post('/fairu/upload-meta-bulk', [AssetController::class, 'uploadMetaBulk'])->name('upload_meta_bulk');
        Route::post('/fairu/folders', [AssetController::class, 'folderContent'])->name('folder');
        Route::post('/fairu/folders/create', [AssetController::class, 'createFolder'])->name('folder_create');
        Route::get('/fairu/folders/{id}', [AssetController::class, 'getFolder'])->name('folder_get');
        Route::post('/fairu/folders/{id}', [AssetController::class, 'updateFolder'])->name('folder_update');
        Route::get('/fairu/files/{id}', [AssetController::class, 'getFile'])->name('file');
        Route::post('/fairu/files/list', [AssetController::class, 'getFilesList'])->name('files-list');
    });
