<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.store');
Route::post('legacy/checklogin', [AuthController::class, 'checkLogin'])->name('legacy.checklogin');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('permission:35')->group(function (): void {
        Route::get('masters/{masterKey}', [MasterController::class, 'index'])->name('masters.index');
        Route::get('masters/{masterKey}/create', [MasterController::class, 'create'])->name('masters.create');
        Route::post('masters/{masterKey}', [MasterController::class, 'store'])->name('masters.store');
        Route::get('masters/{masterKey}/{uuid}', [MasterController::class, 'show'])->name('masters.show');
        Route::get('masters/{masterKey}/{uuid}/edit', [MasterController::class, 'edit'])->name('masters.edit');
        Route::put('masters/{masterKey}/{uuid}', [MasterController::class, 'update'])->name('masters.update');
        Route::delete('masters/{masterKey}/{uuid}', [MasterController::class, 'destroy'])->name('masters.destroy');
        Route::patch('masters/{masterKey}/{uuid}/toggle', [MasterController::class, 'toggle'])->name('masters.toggle');
    });

    Route::get('applications', ComingSoonController::class)->middleware('permission:5,8,9,10,32,33')->defaults('module', 'applications')->name('applications.index');
    Route::get('workflow', ComingSoonController::class)->middleware('permission:6,20,21,27,28,38')->defaults('module', 'workflow')->name('workflow.index');
    Route::get('reports', ComingSoonController::class)->middleware('permission:16,34,39')->defaults('module', 'reports')->name('reports.index');
    Route::get('settings', ComingSoonController::class)->middleware('permission:1,2,4')->defaults('module', 'settings')->name('settings.index');
});
