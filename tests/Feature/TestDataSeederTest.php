<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Check;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TestDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_data_is_clearly_identified_and_consistent(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertSame(2, Agency::testData()->count());
        $this->assertTrue(Agency::all()->every(fn ($a) => str_starts_with($a->name, '[TEST] ') && str_starts_with($a->slug, 'test-')));
        $this->assertTrue(User::all()->every(fn ($u) => str_ends_with($u->email, '@example.com')));
        $this->assertTrue(Site::all()->every(fn ($s) => preg_match('#^https://example\.(com|org|net)$#', $s->url) === 1));

        // Relations réelles : chaque site appartient à l'agence de son client.
        Site::with('client')->get()->each(fn ($site) => $this->assertSame($site->client->agency_id, $site->agency_id));

        // Aucun résultat de surveillance inventé.
        $this->assertSame(0, Check::count());
        $this->assertSame(0, Incident::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TestDataSeeder::class);
        $this->seed(TestDataSeeder::class);

        $this->assertSame(2, Agency::count());
        $this->assertSame(6, Site::count());
    }

    public function test_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        (new TestDataSeeder)->run();
    }

    public function test_purge_removes_only_test_data(): void
    {
        $realClient = Client::factory()->create(['name' => 'Vrai client']);
        $this->seed(TestDataSeeder::class);

        $this->artisan('uptime:purge-test-data', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Agency::testData()->count());
        $this->assertSame(0, Site::count());
        $this->assertNull(Plan::where('code', 'test')->first());
        $this->assertNotNull(Client::find($realClient->id));
    }
}
