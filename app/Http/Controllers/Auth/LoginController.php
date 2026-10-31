<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        // Compte inconnu, mot de passe faux ou compte désactivé : même message (pas d'énumération).
        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            Log::channel('security')->info('Échec de connexion', ['email' => $credentials['email'], 'ip' => $request->ip()]);
            if ($known = User::where('email', $credentials['email'])->first()) {
                AuditLog::record('auth.login_failed', $known, ['ip' => $request->ip()]);
            }

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login', $user);

        return redirect()->intended(route($user->isSuperAdmin() ? 'admin.index' : 'dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            AuditLog::record('auth.logout', $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Vous êtes déconnecté.');
    }
}
