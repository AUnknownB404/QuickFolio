<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioExportController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// Template Marketplace (Public)
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('/templates/{template:slug}', [TemplateController::class, 'show'])->name('templates.show');
Route::get('/preview/{template:slug}', [TemplateController::class, 'preview'])->name('templates.preview');

// Authenticated Portfolio Management & Editor Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PortfolioController::class, 'index'])->name('dashboard');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/templates/{template}/download-demo', [PortfolioExportController::class, 'downloadDemo'])->name('templates.download-demo');
        Route::post('/templates', [AdminController::class, 'storeTemplate'])->name('templates.store');
        Route::patch('/templates/{template}', [AdminController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}', [AdminController::class, 'destroyTemplate'])->name('templates.destroy');
    });

    // Create portfolio from template
    Route::post('/templates/{template:slug}/use', [PortfolioController::class, 'store'])->name('templates.use');

    // Portfolio Editor & Management
    Route::get('/portfolios/{portfolio}/edit', [PortfolioController::class, 'edit'])->name('portfolios.edit');
    Route::patch('/portfolios/{portfolio}', [PortfolioController::class, 'update'])->name('portfolios.update');
    Route::delete('/portfolios/{portfolio}', [PortfolioController::class, 'destroy'])->name('portfolios.destroy');

    // Template switching
    Route::post('/portfolios/{portfolio}/switch-template', [PortfolioController::class, 'switchTemplate'])->name('portfolios.switch-template');
    Route::post('/portfolios/{portfolio}/publish', [PortfolioController::class, 'publish'])->name('portfolios.publish');

    // Download ZIP export
    Route::get('/portfolios/{portfolio}/download', [PortfolioExportController::class, 'download'])->name('portfolios.download');

    // File / Image Upload
    Route::post('/portfolios/{portfolio}/upload', [UploadController::class, 'store'])->name('portfolios.upload');
});

// Public Published Portfolio View
Route::get('/p/{portfolio:slug}', [PortfolioController::class, 'showPublished'])->name('portfolios.published');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
