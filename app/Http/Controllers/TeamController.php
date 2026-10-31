<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Utilisateurs d'une agence. Toutes les règles sont appliquées ici, côté serveur :
 * - l'utilisateur cible appartient toujours à l'agence courante (liaison {member}) ;
 * - seuls les rôles d'agence sont attribuables, jamais super administrateur ;
 * - personne ne modifie son propre rôle ni son propre statut ;
 * - l'agence garde toujours au moins un administrateur actif.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('users.view');

        return view('team.index', [
            'members' => User::where('agency_id', $request->user()->agency_id)->orderByDesc('is_active')->orderBy('name')->get(),
            'me' => $request->user(),
            'limit' => $this->userLimit($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('users.manage');

        return view('team.form', ['member' => new User, 'roles' => RoleName::assignableInAgency(), 'me' => $request->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.manage');
        $actor = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ], ['email.unique' => 'Cette adresse est déjà utilisée par un compte Uptime Africa.'], ['role' => 'rôle']);

        $limit = $this->userLimit($actor);
        if ($limit !== null && User::where('agency_id', $actor->agency_id)->count() >= $limit) {
            throw ValidationException::withMessages(['email' => "Votre formule est limitée à {$limit} utilisateur(s)."]);
        }

        $member = DB::transaction(function () use ($data, $actor) {
            $member = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::password(40)]);
            $member->agency_id = $actor->agency_id;
            $member->is_active = true;
            $member->assignRole(RoleName::from($data['role']))->save();

            AuditLog::record('user.invited', $member, ['email' => $member->email, 'role' => $data['role']]);

            return $member;
        });

        $this->sendInvitation($member, $actor);

        return redirect()->route('team.index')->with('status', "Invitation envoyée à {$member->email}. Le lien est valable 48 heures.");
    }

    public function edit(Request $request, User $member): View
    {
        $this->authorize('users.manage');

        return view('team.form', ['member' => $member, 'roles' => RoleName::assignableInAgency(), 'me' => $request->user()]);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $this->authorize('users.manage');
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ], [], ['role' => 'rôle']);

        $newRole = RoleName::from($data['role']);
        $oldRole = $member->role->slug;

        if ($newRole->value !== $oldRole) {
            if ($member->is($actor)) {
                $this->deny('Vous ne pouvez pas modifier votre propre rôle.', $member, 'role');
            }
            if ($oldRole === RoleName::Admin->value) {
                $this->ensureAnotherActiveAdmin($member, 'role');
            }
        }

        $member->name = $data['name'];
        $member->assignRole($newRole)->save();

        if ($newRole->value !== $oldRole) {
            AuditLog::record('user.role_changed', $member, ['from' => $oldRole, 'to' => $newRole->value]);
        } else {
            AuditLog::record('user.updated', $member);
        }

        return redirect()->route('team.index')->with('status', 'Utilisateur mis à jour.');
    }

    public function toggle(Request $request, User $member): RedirectResponse
    {
        $this->authorize('users.manage');

        if ($member->is($request->user())) {
            $this->deny('Vous ne pouvez pas désactiver votre propre compte.', $member);
        }
        if ($member->is_active && $member->hasRole(RoleName::Admin)) {
            $this->ensureAnotherActiveAdmin($member);
        }

        $member->is_active = ! $member->is_active;
        if (! $member->is_active) {
            $member->setRememberToken(Str::random(60));
        }
        $member->save();

        $revoked = $member->is_active ? 0 : Sessions::revokeAll($member);
        AuditLog::record($member->is_active ? 'user.reactivated' : 'user.deactivated', $member, $member->is_active ? [] : ['sessions_revoked' => $revoked]);

        return back()->with('status', $member->is_active ? "{$member->name} est réactivé." : "{$member->name} est désactivé et déconnecté.");
    }

    public function resendInvitation(Request $request, User $member): RedirectResponse
    {
        $this->authorize('users.manage');

        if ($member->last_login_at !== null || ! $member->is_active) {
            return back()->with('error', 'Ce compte est déjà activé ou désactivé : aucune invitation à renvoyer.');
        }

        $this->sendInvitation($member, $request->user());
        AuditLog::record('user.invitation_resent', $member);

        return back()->with('status', "Invitation renvoyée à {$member->email}.");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->authorize('users.manage');

        if ($member->is($request->user())) {
            $this->deny('Vous ne pouvez pas supprimer votre propre compte.', $member);
        }
        if ($member->is_active && $member->hasRole(RoleName::Admin)) {
            $this->ensureAnotherActiveAdmin($member);
        }

        AuditLog::record('user.deleted', $member, ['email' => $member->email, 'role' => $member->role->slug]);
        Sessions::revokeAll($member);
        DB::table(config('auth.passwords.users.table'))->where('email', $member->email)->delete();
        $member->delete();

        return redirect()->route('team.index')->with('status', 'Utilisateur supprimé. Son historique d\'actions est conservé.');
    }

    private function sendInvitation(User $member, User $actor): void
    {
        $token = Password::broker('invitations')->createToken($member);
        $member->notify(new InvitationNotification($token, $actor->agency->name, $actor->name));
    }

    /** @return list<string> */
    private function assignableRoles(): array
    {
        return array_map(fn (RoleName $role) => $role->value, RoleName::assignableInAgency());
    }

    private function ensureAnotherActiveAdmin(User $member, string $field = 'member'): void
    {
        $adminRoleId = Role::findBySlug(RoleName::Admin)->id;
        $others = User::where('agency_id', $member->agency_id)->where('role_id', $adminRoleId)
            ->where('is_active', true)->whereKeyNot($member->getKey())->count();

        if ($others === 0) {
            $this->deny('L\'agence doit garder au moins un administrateur actif.', $member, $field);
        }
    }

    private function deny(string $message, User $member, string $field = 'member'): never
    {
        AuditLog::record('user.change_refused', $member, ['reason' => $message]);

        throw ValidationException::withMessages([$field => $message]);
    }

    private function userLimit(User $user): ?int
    {
        return $user->agency->currentSubscription()->with('plan:id,max_users')->first()?->plan?->max_users;
    }
}
