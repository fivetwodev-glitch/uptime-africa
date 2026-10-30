<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterAgencyController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/inscription', [RegisterAgencyController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterAgencyController::class, 'store'])->middleware('throttle:register');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');

    Route::middleware('agency')->group(function () {
        Route::get('/tableau-de-bord', DashboardController::class)->name('dashboard');

        Route::patch('/sites/{site}/surveillance', [SiteController::class, 'toggle'])->name('sites.toggle');
        Route::resource('sites', SiteController::class);

        Route::resource('clients', ClientController::class);

        Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::post('/incidents/{incident}/prise-en-charge', [IncidentController::class, 'acknowledge'])->name('incidents.acknowledge');

        Route::get('/controles', [CheckController::class, 'index'])->name('checks.index');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/canaux', [NotificationController::class, 'store'])->name('notifications.channels.store');
        Route::put('/notifications/canaux/{channel}/preferences', [NotificationController::class, 'updatePreference'])->name('notifications.channels.preferences');
        Route::patch('/notifications/canaux/{channel}', [NotificationController::class, 'toggle'])->name('notifications.channels.toggle');
        Route::delete('/notifications/canaux/{channel}', [NotificationController::class, 'destroy'])->name('notifications.channels.destroy');

        Route::get('/abonnement', SubscriptionController::class)->name('subscription.show');

        Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/parametres/agence', [SettingsController::class, 'updateAgency'])->name('settings.agency');
        Route::put('/parametres/profil', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/parametres/mot-de-passe', [SettingsController::class, 'updatePassword'])->name('settings.password');
    });
});
