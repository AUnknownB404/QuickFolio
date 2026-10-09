<?php

use App\Http\Controllers\CertificationController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioProfileController;
use App\Http\Controllers\PortfolioSkillController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSkillController;
use App\Http\Controllers\SocialLinkController;
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
    Route::resource('portfolios.certifications', CertificationController::class)->scoped();
    Route::resource('portfolios.experiences', ExperienceController::class)->scoped();
    Route::resource('portfolios.educations', EducationController::class)->scoped();
    Route::resource('portfolios.projects', ProjectController::class)->scoped();
    Route::resource('portfolios.projects.skills', ProjectSkillController::class)
        ->only(['index', 'store', 'destroy'])
        ->scoped();
    Route::resource('portfolios.skills', PortfolioSkillController::class)->scoped();
    Route::resource('portfolios.social-links', SocialLinkController::class)->scoped();

    Route::get('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'show'])->name('portfolios.profile.show');
    Route::get('portfolios/{portfolio}/profile/create', [PortfolioProfileController::class, 'create'])->name('portfolios.profile.create');
    Route::post('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'store'])->name('portfolios.profile.store');
    Route::get('portfolios/{portfolio}/profile/edit', [PortfolioProfileController::class, 'edit'])->name('portfolios.profile.edit');
    Route::put('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'update'])->name('portfolios.profile.update');
    Route::delete('portfolios/{portfolio}/profile', [PortfolioProfileController::class, 'destroy'])->name('portfolios.profile.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
