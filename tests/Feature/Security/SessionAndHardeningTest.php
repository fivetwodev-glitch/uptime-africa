<?php

namespace Tests\Feature\Security;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Interface\InterfaceTestCase;

class SessionAndHardeningTest extends InterfaceTestCase
{
    public function test_passwords_are_hashed(): void
    {
        $this->post(route('register'), [
            'agency_name' => 'Agence Hash', 'name' => 'Ama', 'email' => 'ama@exemple.ci',
            'password' => 'MotDePasse-2026', 'password_confirmation' => 'MotDePasse-2026',
        ]);

        $stored = DB::table('users')->where('email', 'ama@exemple.ci')->value('password');
        $this->assertNotSame('MotDePasse-2026', $stored);
        $this->assertSame('bcrypt', Hash::info($stored)['algoName']);
        $this->assertTrue(Hash::check('MotDePasse-2026', $stored));
    }

    public function test_session_is_regenerated_at_login_and_invalidated_at_logout(): void
    {
        $user = $this->userFor();
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->assertNotSame($before, session()->getId());

        $this->post(route('logout'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_session_cookie_is_http_only_and_same_site(): void
    {
        config(['session.driver' => 'database']);
        $cookie = collect($this->get(route('login'))->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        // En production : cookie HTTPS uniquement et contenu de session chiffré.
        $production = file_get_contents(base_path('.env.production.example'));
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $production);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $production);
    }

    public function test_csrf_protection_is_active_on_web_routes(): void
    {
        $this->assertContains(ValidateCsrfToken::class, app(Kernel::class)->getMiddlewareGroups()['web']);
        $this->get(route('login'))->assertSee('name="_token"', false);
    }

    public function test_changing_password_and_deactivating_close_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->userFor();
        $member = $this->userFor($admin->agency, RoleName::Collaborator);
        DB::table('sessions')->insert([
            ['id' => 'admin-autre-appareil', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'membre-telephone', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $this->actingAs($admin);

        $this->put(route('settings.password'), ['current_password' => 'password', 'password' => 'Nouveau-Pass-2026', 'password_confirmation' => 'Nouveau-Pass-2026'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-autre-appareil']);

        $this->patch(route('team.toggle', $member))->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'membre-telephone']);

        // Un compte désactivé ne peut plus ni naviguer ni se reconnecter.
        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_changing_email_requires_the_current_password(): void
    {
        $user = $this->userFor();
        $this->actingAs($user);

        $this->put(route('settings.profile'), ['name' => $user->name, 'email' => 'nouvelle@exemple.ci'])->assertSessionHasErrorsIn('profile', 'current_password');
        $this->put(route('settings.profile'), ['name' => $user->name, 'email' => 'nouvelle@exemple.ci', 'current_password' => 'faux'])->assertSessionHasErrorsIn('profile', 'current_password');
        $this->assertNotSame('nouvelle@exemple.ci', $user->fresh()->email);

        $this->put(route('settings.profile'), ['name' => $user->name, 'email' => 'nouvelle@exemple.ci', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('nouvelle@exemple.ci', $user->fresh()->email);
    }

    public function test_html_is_escaped_in_pages(): void
    {
        $user = $this->userFor();
        Client::factory()->create(['agency_id' => $user->agency_id, 'name' => '<script>alert("xss")</script>']);

        $this->actingAs($user)->get(route('clients.index'))
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_search_input_cannot_inject_sql(): void
    {
        $user = $this->userFor();
        $this->siteFor($user->agency, ['name' => 'Mon site']);
        $this->siteFor($this->userFor()->agency, ['name' => 'Site étranger']);

        foreach (["' OR '1'='1", "%' OR 1=1 --", "x'); DROP TABLE sites; --"] as $payload) {
            $this->actingAs($user)->get(route('sites.index', ['q' => $payload]))->assertOk()->assertDontSee('Site étranger');
            $this->actingAs($user)->get(route('clients.index', ['q' => $payload]))->assertOk();
        }
        $this->assertSame(2, DB::table('sites')->count());
    }

    public function test_sensitive_actions_are_journaled(): void
    {
        $user = $this->userFor();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'));

        $actions = AuditLog::where('subject_id', (string) $user->id)->pluck('action')->all();
        foreach (['auth.login_failed', 'auth.login', 'auth.logout'] as $action) {
            $this->assertContains($action, $actions);
        }
        $this->assertSame($user->agency_id, AuditLog::where('action', 'auth.login')->value('agency_id'));
    }

    public function test_super_admin_suspension_locks_out_the_agency(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = $this->userFor();

        $this->actingAs($superAdmin)->patch(route('admin.agencies.toggle', $user->agency))->assertRedirect();
        $this->assertFalse($user->agency->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.suspended']);

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user->fresh())->patch(route('admin.agencies.toggle', $user->agency))->assertForbidden();
    }
}
