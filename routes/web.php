<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UptimeRobotController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterAgencyController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SyncTriggerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Webhook de statut WhatsApp Cloud API (vérification par jeton, contenu signé par Meta).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:whatsapp-webhook');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:whatsapp-webhook')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('webhooks.whatsapp');

// Déclencheur externe protégé par jeton (alternative au cron Hostinger). Désactivé sans UPTIME_SYNC_TRIGGER_TOKEN.
Route::post('/declencheur/synchronisation', SyncTriggerController::class)
    ->middleware('throttle:sync-trigger')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('sync.trigger');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/inscription', [RegisterAgencyController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterAgencyController::class, 'store'])->middleware('throttle:register');

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reinitialisation/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialisation', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
});

// auth.session : une session dont le mot de passe a changé depuis est fermée.
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::patch('/admin/agences/{agency}/statut', [AdminController::class, 'toggleAgency'])->name('admin.agencies.toggle');
    Route::get('/admin/uptimerobot', [UptimeRobotController::class, 'index'])->name('admin.uptimerobot');
    Route::post('/admin/uptimerobot/test', [UptimeRobotController::class, 'test'])->middleware('throttle:6,1')->name('admin.uptimerobot.test');
    Route::post('/admin/uptimerobot/synchroniser', [UptimeRobotController::class, 'sync'])->middleware('throttle:3,1')->name('admin.uptimerobot.sync');

    Route::middleware('agency')->group(function () {
        Route::get('/tableau-de-bord', DashboardController::class)->name('dashboard');

        Route::patch('/sites/{site}/surveillance', [SiteController::class, 'toggle'])->name('sites.toggle');
        Route::post('/sites/synchroniser', [SiteController::class, 'syncAll'])->middleware('throttle:2,1')->name('sites.sync-all');
        Route::post('/sites/{site}/synchroniser', [SiteController::class, 'sync'])->middleware('throttle:6,1')->name('sites.sync');
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
        Route::post('/notifications/canaux/{channel}/test', [NotificationController::class, 'test'])->middleware('throttle:notification-test')->name('notifications.channels.test');
        Route::put('/notifications/parametres', [NotificationController::class, 'updateSettings'])->name('notifications.settings');

        Route::get('/abonnement', SubscriptionController::class)->name('subscription.show');

        Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/parametres/agence', [SettingsController::class, 'updateAgency'])->name('settings.agency');
        Route::put('/parametres/profil', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/parametres/mot-de-passe', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::get('/parametres/securite', [SettingsController::class, 'security'])->name('settings.security');
        Route::delete('/parametres/sessions', [SettingsController::class, 'logoutOtherSessions'])->name('settings.sessions.destroy');

        Route::get('/parametres/equipe', [TeamController::class, 'index'])->name('team.index');
        Route::get('/parametres/equipe/ajouter', [TeamController::class, 'create'])->name('team.create');
        Route::post('/parametres/equipe', [TeamController::class, 'store'])->name('team.store');
        Route::get('/parametres/equipe/{member}/modifier', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('/parametres/equipe/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::patch('/parametres/equipe/{member}/statut', [TeamController::class, 'toggle'])->name('team.toggle');
        Route::post('/parametres/equipe/{member}/invitation', [TeamController::class, 'resendInvitation'])->name('team.invitation');
        Route::delete('/parametres/equipe/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
});
