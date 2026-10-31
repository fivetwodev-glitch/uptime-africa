<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringProvider;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\UptimeRobot\UptimeRobotClient;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const TIMEZONES = [
        'Africa/Abidjan' => 'Abidjan, Dakar, Bamako, Ouagadougou, Lomé, Conakry (UTC+0)',
        'Africa/Lagos' => 'Cotonou, Niamey, Douala, Libreville, Kinshasa (UTC+1)',
        'Africa/Casablanca' => 'Casablanca (UTC+1)',
        'Africa/Tunis' => 'Tunis, Alger (UTC+1)',
        'Africa/Lubumbashi' => 'Lubumbashi, Bujumbura, Kigali (UTC+2)',
        'Europe/Paris' => 'Paris (UTC+1/+2)',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('settings.index', [
            'user' => $user,
            'agency' => $user->agency,
            'timezones' => self::TIMEZONES,
            'uptimeRobotConfigured' => app(UptimeRobotClient::class)->isConfigured(),
            'uptimeRobotSites' => Site::where('monitoring_provider', MonitoringProvider::UptimeRobot)
                ->toBase()->selectRaw('sync_status, count(*) as total')->groupBy('sync_status')->pluck('total', 'sync_status'),
        ]);
    }

    public function security(Request $request): View
    {
        return view('settings.security', [
            'sessions' => Sessions::forUser($request->user()),
            'currentSession' => $request->session()->getId(),
            'sessionsEnabled' => Sessions::enabled(),
        ]);
    }

    public function updateAgency(Request $request): RedirectResponse
    {
        $this->authorize('agency.update');

        $agency = $request->user()->agency;
        $agency->update($request->validateWithBag('agency', [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{6,32}$/'],
            'timezone' => ['required', Rule::in(array_keys(self::TIMEZONES))],
        ], ['contact_phone.regex' => 'Le numéro de téléphone est invalide.'], [
            'name' => 'nom de l\'agence', 'contact_email' => 'email de contact', 'contact_phone' => 'téléphone', 'timezone' => 'fuseau horaire',
        ]));

        AuditLog::record('agency.updated', $agency, ['fields' => array_keys($agency->getChanges())]);

        return back()->with('status', 'Informations de l\'agence enregistrées.');
    }

    /**
     * Seuls le nom et l'email sont modifiables : rôle, agence et statut sont ignorés
     * même s'ils sont envoyés (ni validés, ni assignables en masse).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user)],
            // Changer d'email permet de recevoir les liens de réinitialisation : mot de passe exigé.
            'current_password' => [Rule::requiredIf(fn () => mb_strtolower((string) $request->input('email')) !== $user->email), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Saisissez votre mot de passe actuel pour changer d\'adresse email.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
        ], ['current_password' => 'mot de passe actuel']);

        $previousEmail = $user->email;
        $user->fill(['name' => $data['name'], 'email' => $data['email']])->save();

        AuditLog::record($user->email !== $previousEmail ? 'user.email_changed' : 'user.profile_updated', $user,
            $user->email !== $previousEmail ? ['from' => $previousEmail, 'to' => $user->email] : []);

        return back()->with('status', 'Profil enregistré.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
        ], ['current_password' => 'mot de passe actuel', 'password' => 'nouveau mot de passe']);

        $user = $request->user();
        $user->password = $data['password'];
        $user->save();

        // Les autres appareils sont déconnectés ; la session courante est renouvelée.
        $revoked = Sessions::revokeAll($user, except: $request->session()->getId());
        $request->session()->regenerate();
        AuditLog::record('user.password_changed', $user, ['sessions_revoked' => $revoked]);

        return back()->with('status', 'Mot de passe modifié. Vos autres sessions ont été fermées.');
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validateWithBag('sessions', [
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => 'Le mot de passe est incorrect.'], ['password' => 'mot de passe']);

        $user = $request->user();
        Auth::logoutOtherDevices($request->input('password'));
        $revoked = Sessions::revokeAll($user, except: $request->session()->getId());
        AuditLog::record('user.sessions_revoked', $user, ['count' => $revoked]);

        return back()->with('status', $revoked > 0 ? "{$revoked} autre(s) session(s) fermée(s)." : 'Aucune autre session ouverte.');
    }
}
