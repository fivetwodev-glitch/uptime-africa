<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'invitation' => $request->boolean('invitation'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['password' => 'mot de passe']);

        // Le délai long des invitations ne s'applique qu'à un compte jamais utilisé.
        $neverUsed = User::where('email', $data['email'])->whereNull('last_login_at')->exists();
        $broker = Password::broker($neverUsed ? 'invitations' : 'users');

        $status = $broker->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token'], 'is_active' => true],
            function (User $user, string $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Toute session ouverte avec l'ancien mot de passe est fermée.
                Sessions::revokeAll($user);
                AuditLog::record('auth.password_reset', $user);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            Log::channel('security')->warning('Réinitialisation refusée', ['email' => $data['email'], 'ip' => $request->ip(), 'status' => $status]);

            throw ValidationException::withMessages(['email' => 'Ce lien est invalide ou a expiré. Faites une nouvelle demande.']);
        }

        return redirect()->route('login')->with('status', 'Mot de passe enregistré. Vous pouvez vous connecter.');
    }
}
