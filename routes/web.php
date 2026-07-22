<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ScholarshipController;
use App\Http\Controllers\ScholarshipDocumentController;
use App\Http\Controllers\ScholarshipReportController;
use App\Http\Controllers\ScholarshipWorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.store');
Route::get('csc/login', [AuthController::class, 'redirectToCsc'])->name('csc.login');
Route::get('csc/callback', [AuthController::class, 'cscCallback'])->name('csc.callback');
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

    Route::middleware('permission:5,8,9,10,32,33')->group(function (): void {
        Route::get('applications', [ScholarshipController::class, 'index'])->name('applications.index');
        Route::get('applications/create', [ScholarshipController::class, 'create'])->name('applications.create');
        Route::get('applications/create/{scheme}', [ScholarshipController::class, 'createForScheme'])->name('applications.create.scheme');
        Route::post('applications', [ScholarshipController::class, 'store'])->name('applications.store');
    });

    Route::middleware('permission:5,6,8,9,10,20,21,27,28,32,33,38')->group(function (): void {
        Route::get('applications/{application}/documents/{document}', [ScholarshipDocumentController::class, 'show'])->name('applications.documents.show');
        Route::get('applications/{application}/documents/{document}/download', [ScholarshipDocumentController::class, 'download'])->name('applications.documents.download');
        Route::get('applications/{application}', [ScholarshipController::class, 'show'])->name('applications.show');
    });

    Route::middleware('permission:5,8,9,10,32,33')->group(function (): void {
        Route::get('applications/{application}/edit', [ScholarshipController::class, 'edit'])->name('applications.edit');
        Route::put('applications/{application}', [ScholarshipController::class, 'update'])->name('applications.update');
        Route::post('applications/{application}/submit', [ScholarshipController::class, 'submit'])->name('applications.submit');
        Route::get('applications/{application}/wallet', [ScholarshipController::class, 'walletRedirect'])->name('applications.wallet.redirect');
        Route::match(['get', 'post'], 'applications/{application}/wallet/callback', [ScholarshipController::class, 'walletCallback'])->name('applications.wallet.callback');
    });

    Route::middleware('permission:6,20,21,27,28,38')->group(function (): void {
        Route::get('workflow', [ScholarshipWorkflowController::class, 'index'])->name('workflow.index');
        Route::post('workflow/applications/{application}/action', [ScholarshipWorkflowController::class, 'action'])->name('workflow.action');
        Route::post('workflow/ic-batches', [ScholarshipWorkflowController::class, 'icBatch'])->name('workflow.ic-batches.store');
        Route::post('workflow/payment-batches', [ScholarshipWorkflowController::class, 'paymentBatch'])->name('workflow.payment-batches.store');
        Route::post('workflow/applications/{application}/payment-result', [ScholarshipWorkflowController::class, 'paymentResult'])->name('workflow.payment-result');
    });

    Route::get('reports', [ScholarshipReportController::class, 'index'])->middleware('permission:16,34,39')->name('reports.index');
    Route::get('settings', ComingSoonController::class)->middleware('permission:1,2,4')->defaults('module', 'settings')->name('settings.index');
});
