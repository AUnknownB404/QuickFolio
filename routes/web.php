<?php

use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('portfolios', PortfolioController::class);

    Route::get('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'show'])->name('portfolios.profile.show');
    Route::get('portfolios/{portfolio}/profile/create', [PortfolioProfileController::class, 'create'])->name('portfolios.profile.create');
    Route::post('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'store'])->name('portfolios.profile.store');
    Route::get('portfolios/{portfolio}/profile/edit', [PortfolioProfileController::class, 'edit'])->name('portfolios.profile.edit');
    Route::put('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'update'])->name('portfolios.profile.update');
    Route::delete('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'destroy'])->name('portfolios.profile.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
