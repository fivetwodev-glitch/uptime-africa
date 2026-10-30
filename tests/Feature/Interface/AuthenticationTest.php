<?php

namespace Tests\Feature\Interface;

use App\Enums\RoleName;
use App\Models\Agency;
use App\Models\User;

class AuthenticationTest extends InterfaceTestCase
{
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('sites.index'))->assertRedirect(route('login'));
    }

    public function test_login_and_register_pages_are_displayed(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
        $this->get(route('register'))->assertOk()->assertSee('Créer votre agence');
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = $this->userFor();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $user = $this->userFor();

        $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'mauvais'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        $user = $this->userFor();
        $user->forceFill(['is_active' => false])->save();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = $this->userFor();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->userFor();

        foreach (range(1, 5) as $_) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_registration_creates_an_agency_and_its_owner(): void
    {
        $this->post(route('register'), [
            'agency_name' => 'Agence Lagune',
            'name' => 'Awa Koné',
            'email' => 'awa@lagune.ci',
            'password' => 'MotDePasse-2026',
            'password_confirmation' => 'MotDePasse-2026',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'awa@lagune.ci')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(RoleName::Owner));
        $this->assertSame('Agence Lagune', $user->agency->name);
        $this->assertFalse($user->agency->is_test_data);
    }

    public function test_super_admin_is_sent_to_the_administration(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Agency::factory()->create(['name' => 'Agence visible']);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.index'));
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Agence visible');
    }

    public function test_agency_users_cannot_open_the_administration(): void
    {
        $this->actingAs($this->userFor())->get(route('admin.index'))->assertForbidden();
    }
}
