<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'team' => User::where('agency_id', $user->agency_id)->orderBy('name')->get(),
            'timezones' => self::TIMEZONES,
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

        AuditLog::record('agency.updated', $agency);

        return back()->with('status', 'Informations de l\'agence enregistrées.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($user)],
        ]));

        AuditLog::record('user.profile_updated', $user);

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

        AuditLog::record('user.password_changed', $user);

        return back()->with('status', 'Mot de passe modifié.');
    }
}
