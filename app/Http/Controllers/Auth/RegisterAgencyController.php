<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Création d'une agence et de son propriétaire.
 */
class RegisterAgencyController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agency_name' => ['required', 'string', 'min:2', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'agency_name' => 'nom de l\'agence',
        ]);

        $user = DB::transaction(function () use ($data) {
            $agency = new Agency(['name' => $data['agency_name'], 'slug' => $this->uniqueSlug($data['agency_name']), 'contact_email' => $data['email']]);
            $agency->save();

            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->agency_id = $agency->id;
            $user->assignRole(RoleName::Owner)->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('agency.registered', $user->agency);

        return redirect()->route('dashboard')->with('status', 'Bienvenue ! Votre agence est créée. Commencez par ajouter un client.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agence';
        $slug = $base;
        $i = 2;

        while (Agency::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
