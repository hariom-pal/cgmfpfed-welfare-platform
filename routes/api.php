<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\ScholarshipLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:masters.manage'])->prefix('masters')->name('api.masters.')->group(function (): void {
    Route::get('{masterKey}', [MasterController::class, 'index'])->name('index');
    Route::get('{masterKey}/{uuid}', [MasterController::class, 'show'])->name('show');
});

Route::prefix('scholarship/lookups')->name('api.scholarship.lookups.')->group(function (): void {
    Route::get('district-unions', [ScholarshipLookupController::class, 'districtUnions'])->name('district-unions');
    Route::get('samitis', [ScholarshipLookupController::class, 'samitis'])->name('samitis');
    Route::get('phads', [ScholarshipLookupController::class, 'phads'])->name('phads');
    Route::get('blocks', [ScholarshipLookupController::class, 'blocks'])->name('blocks');
    Route::get('gram-panchayats', [ScholarshipLookupController::class, 'gramPanchayats'])->name('gram-panchayats');
    Route::get('villages', [ScholarshipLookupController::class, 'villages'])->name('villages');
    Route::get('cities', [ScholarshipLookupController::class, 'cities'])->name('cities');
    Route::get('wards', [ScholarshipLookupController::class, 'wards'])->name('wards');
});
