<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocalAuthController;
use App\Http\Controllers\MasterController;
use App\Http\Middleware\EnsureLocalAdminAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (session()->get('local_admin_authenticated') === true) {
        return redirect()->route('dashboard');
    }

    return view('auth.login');
});

Route::get('login', [LocalAuthController::class, 'showLogin'])->name('login');
Route::post('login', [LocalAuthController::class, 'login'])->name('login.store');
Route::post('logout', [LocalAuthController::class, 'logout'])->name('logout');

Route::middleware(EnsureLocalAdminAuthenticated::class)->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('masters/{masterKey}', [MasterController::class, 'index'])->name('masters.index');
    Route::get('masters/{masterKey}/create', [MasterController::class, 'create'])->name('masters.create');
    Route::post('masters/{masterKey}', [MasterController::class, 'store'])->name('masters.store');
    Route::get('masters/{masterKey}/{uuid}', [MasterController::class, 'show'])->name('masters.show');
    Route::get('masters/{masterKey}/{uuid}/edit', [MasterController::class, 'edit'])->name('masters.edit');
    Route::put('masters/{masterKey}/{uuid}', [MasterController::class, 'update'])->name('masters.update');
    Route::delete('masters/{masterKey}/{uuid}', [MasterController::class, 'destroy'])->name('masters.destroy');
    Route::patch('masters/{masterKey}/{uuid}/toggle', [MasterController::class, 'toggle'])->name('masters.toggle');
    Route::view('reports', 'placeholder', ['title' => 'Reports'])->name('reports.index');
    Route::view('settings', 'placeholder', ['title' => 'Settings'])->name('settings.index');
});
