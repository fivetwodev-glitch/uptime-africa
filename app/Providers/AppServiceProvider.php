<?php

namespace App\Providers;

use App\Models\User;
use App\Services\UptimeRobot\UptimeRobotClient;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête (ou par commande).
        $this->app->scoped(TenantContext::class);

        // La clé API n'est lue que depuis la configuration (variable d'environnement).
        $this->app->bind(UptimeRobotClient::class, fn () => UptimeRobotClient::fromConfig());
    }

    public function boot(): void
    {
        // Détecte en développement les accès paresseux et attributs inconnus.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        // Chaque permission de config/permissions.php devient une capacité Gate (@can, middleware can:).
        Gate::before(function (User $user, string $ability) {
            if (array_key_exists($ability, config('permissions.permissions'))) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        // Anti force brute : par compte + IP, par IP, et par compte toutes IP confondues.
        RateLimiter::for('sync-trigger', fn (Request $request) => Limit::perMinute(4)->by('sync-trigger:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perHour(30)->by('login-account:'.mb_strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perHour(5)->by('reset:'.mb_strtolower((string) $request->input('email'))),
            Limit::perHour(20)->by('reset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));

        // {member} : un utilisateur n'est résolu que dans l'agence courante (404 sinon).
        Route::bind('member', function (string $value) {
            $agencyId = app(TenantContext::class)->id();
            abort_if($agencyId === null || ! ctype_digit($value), 404);

            return User::where('agency_id', $agencyId)->findOrFail((int) $value);
        });

        Paginator::defaultView('components.pagination');
    }
}
