<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $email = mb_strtolower($request->validate(['email' => ['required', 'string', 'email', 'max:191']])['email']);

        // Comptes actifs uniquement ; le résultat n'est jamais révélé (pas d'énumération des adresses).
        $status = Password::broker('users')->sendResetLink(['email' => $email, 'is_active' => true]);

        Log::channel('security')->info('Demande de réinitialisation du mot de passe', ['email' => $email, 'ip' => $request->ip(), 'status' => $status]);
        if ($status === Password::RESET_LINK_SENT && $user = User::where('email', $email)->first()) {
            AuditLog::record('auth.password_reset_requested', $user);
        }

        return back()->with('status', 'Si un compte actif correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé. Pensez à vérifier vos courriers indésirables.');
    }
}
