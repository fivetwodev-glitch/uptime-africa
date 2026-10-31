<?php

namespace Tests\Feature\Security;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Interface\InterfaceTestCase;

class PasswordResetTest extends InterfaceTestCase
{
    public function test_forgot_password_page_is_linked_from_login(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié');
    }

    public function test_the_response_is_identical_whether_the_account_exists_or_not(): void
    {
        Notification::fake();
        $user = $this->userFor();
        $inactive = $this->userFor($user->agency);
        $inactive->forceFill(['is_active' => false])->save();

        $known = $this->post(route('password.email'), ['email' => strtoupper($user->email)]);
        $unknown = $this->post(route('password.email'), ['email' => 'personne@exemple.ci']);
        $disabled = $this->post(route('password.email'), ['email' => $inactive->email]);

        foreach ([$known, $unknown, $disabled] as $response) {
            $response->assertRedirect()->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Si un compte actif'));
        }
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertNotSentTo($inactive, ResetPasswordNotification::class);
    }

    public function test_a_valid_link_resets_the_password_once_and_closes_sessions(): void
    {
        $user = $this->userFor();
        $user->forceFill(['last_login_at' => now()])->save();
        $token = Password::broker('users')->createToken($user);
        config(['session.driver' => 'database']);
        \DB::table('sessions')->insert(['id' => 'ancienne-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email, 'password' => 'Nouveau-Pass-2026', 'password_confirmation' => 'Nouveau-Pass-2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nouveau-Pass-2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'ancienne-session']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'subject_id' => (string) $user->id]);

        // Lien à usage unique.
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email, 'password' => 'Autre-Pass-2026', 'password_confirmation' => 'Autre-Pass-2026',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Nouveau-Pass-2026', $user->fresh()->password));
    }

    public function test_invalid_or_expired_links_are_refused(): void
    {
        $user = $this->userFor();
        $user->forceFill(['last_login_at' => now()])->save();
        $payload = fn ($token) => ['token' => $token, 'email' => $user->email, 'password' => 'Nouveau-Pass-2026', 'password_confirmation' => 'Nouveau-Pass-2026'];

        $this->post(route('password.store'), $payload('jeton-invente'))->assertSessionHasErrors('email');

        $token = Password::broker('users')->createToken($user);
        $this->travel(61)->minutes();
        $this->post(route('password.store'), $payload($token))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->post(route('password.email'), ['email' => 'cible@exemple.ci'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'cible@exemple.ci'])->assertStatus(429);
    }

    public function test_an_invited_member_activates_their_account(): void
    {
        Notification::fake();
        $admin = $this->userFor();

        $this->actingAs($admin)->post(route('team.store'), ['name' => 'Aya', 'email' => 'Aya@Exemple.ci', 'role' => 'collaborator'])
            ->assertRedirect(route('team.index'));

        $member = User::where('email', 'aya@exemple.ci')->firstOrFail();
        $this->assertSame($admin->agency_id, $member->agency_id);
        $this->assertTrue($member->hasRole(RoleName::Collaborator));

        $token = null;
        Notification::assertSentTo($member, InvitationNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // L'invitation reste valable 48 h pour un compte jamais utilisé.
        $this->travel(47)->hours();
        auth()->logout();
        $this->post(route('password.store'), [
            'token' => $token, 'email' => 'aya@exemple.ci', 'password' => 'Mon-Pass-2026', 'password_confirmation' => 'Mon-Pass-2026',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'aya@exemple.ci', 'password' => 'Mon-Pass-2026'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($member);
    }
}
