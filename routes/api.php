<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MasterController;
use Illuminate\Support\Facades\Route;

Route::prefix('masters')->name('api.masters.')->group(function (): void {
    Route::get('{masterKey}', [MasterController::class, 'index'])->name('index');
    Route::get('{masterKey}/{uuid}', [MasterController::class, 'show'])->name('show');
});
