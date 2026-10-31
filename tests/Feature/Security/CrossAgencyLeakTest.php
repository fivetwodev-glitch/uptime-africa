<?php

namespace Tests\Feature\Security;

use App\Enums\RoleName;
use App\Models\Agency;
use App\Models\Check;
use App\Models\Client;
use App\Models\Incident;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Interface\InterfaceTestCase;

/**
 * Deux agences complètes (client, site, contrôle, incident, canal, journal, équipe).
 * Chaque utilisateur de l'une tente de lire et de modifier les données de l'autre
 * par toutes les routes : rien ne doit apparaître ni changer.
 */
class CrossAgencyLeakTest extends InterfaceTestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $world = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Alpha', 'Beta'] as $label) {
            $slug = strtolower($label);
            $agency = Agency::factory()->create(['name' => "Agence {$label}"]);
            $admin = User::factory()->create(['agency_id' => $agency->id, 'name' => "Admin {$label}", 'email' => "admin-{$slug}@exemple.ci"]);
            $collaborator = User::factory()->withRole(RoleName::Collaborator)->create(['agency_id' => $agency->id, 'name' => "Collab {$label}", 'email' => "collab-{$slug}@exemple.ci"]);
            $client = Client::factory()->create(['agency_id' => $agency->id, 'name' => "Client secret {$label}"]);
            $site = Site::factory()->create(['client_id' => $client->id, 'name' => "Site secret {$label}", 'url' => "https://secret-{$slug}.exemple.ci"]);
            Check::forceCreate(['agency_id' => $agency->id, 'site_id' => $site->id, 'checked_at' => now()->subMinutes(5), 'is_up' => false,
                'http_status' => 500, 'error_message' => "Erreur secrète {$label}", 'provider' => 'internal']);
            $incident = Incident::factory()->create(['site_id' => $site->id, 'description' => "Incident secret {$label}"]);
            $channel = $this->channelFor($agency, "alertes-{$slug}@exemple.ci");
            NotificationLog::forceCreate(['agency_id' => $agency->id, 'site_id' => $site->id, 'incident_id' => $incident->id,
                'notification_channel_id' => $channel->id, 'channel_type' => 'email', 'recipient' => "destinataire-{$slug}@exemple.ci",
                'event' => 'incident_opened', 'status' => 'sent']);

            $this->world[$label] = compact('agency', 'admin', 'collaborator', 'client', 'site', 'incident', 'channel');
        }
    }

    /** Textes propres à une agence qui ne doivent jamais apparaître chez l'autre. */
    private function secrets(string $label): array
    {
        $slug = strtolower($label);

        return ["Agence {$label}", "Admin {$label}", "collab-{$slug}@exemple.ci", "Client secret {$label}", "Site secret {$label}",
            "secret-{$slug}.exemple.ci", "Erreur secrète {$label}", "Incident secret {$label}", "alertes-{$slug}@exemple.ci", "destinataire-{$slug}@exemple.ci"];
    }

    public static function directions(): array
    {
        return [
            'admin Alpha → Beta' => ['Alpha', 'Beta', 'admin'],
            'collaborateur Alpha → Beta' => ['Alpha', 'Beta', 'collaborator'],
            'admin Beta → Alpha' => ['Beta', 'Alpha', 'admin'],
            'collaborateur Beta → Alpha' => ['Beta', 'Alpha', 'collaborator'],
        ];
    }

    #[DataProvider('directions')]
    public function test_no_page_shows_data_of_the_other_agency(string $own, string $other, string $who): void
    {
        $this->actingAs($this->world[$own][$who]);
        $target = $this->world[$other];

        $urls = [
            route('dashboard'), route('sites.index'), route('clients.index'), route('incidents.index'), route('checks.index', ['periode' => '30j']),
            route('notifications.index'), route('notifications.index', ['onglet' => 'journal']), route('subscription.show'),
            route('settings.index'), route('settings.security'), route('team.index'),
            // Filtres forgés avec les identifiants de l'autre agence.
            route('sites.index', ['client' => $target['client']->id]), route('incidents.index', ['site' => $target['site']->id]),
            route('checks.index', ['site' => $target['site']->id, 'periode' => '30j']), route('sites.create', ['client' => $target['client']->id]),
            route('sites.index', ['q' => 'secret']), route('clients.index', ['q' => 'secret']),
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            if ($response->status() === 403) {
                continue; // page interdite à ce rôle : rien n'est divulgué
            }
            $response->assertOk();
            foreach ($this->secrets($other) as $secret) {
                $response->assertDontSee($secret, false);
            }
        }
    }

    #[DataProvider('directions')]
    public function test_every_route_on_foreign_records_returns_404_and_changes_nothing(string $own, string $other, string $who): void
    {
        $this->actingAs($this->world[$own][$who]);
        $t = $this->world[$other];
        $before = $this->snapshot();

        $requests = [
            ['get', route('sites.show', $t['site'])], ['get', route('sites.edit', $t['site'])],
            ['put', route('sites.update', $t['site']), ['name' => 'Piraté', 'url' => 'https://pirate.ci', 'client_id' => $t['client']->id, 'interval_minutes' => 5, 'timeout_seconds' => 10, 'monitoring_provider' => 'internal']],
            ['patch', route('sites.toggle', $t['site'])], ['delete', route('sites.destroy', $t['site'])],
            ['get', route('clients.show', $t['client'])], ['get', route('clients.edit', $t['client'])],
            ['put', route('clients.update', $t['client']), ['name' => 'Piraté']], ['delete', route('clients.destroy', $t['client'])],
            ['get', route('incidents.show', $t['incident'])], ['post', route('incidents.acknowledge', $t['incident'])],
            ['put', route('notifications.channels.preferences', $t['channel']), ['delay_minutes' => 0]],
            ['patch', route('notifications.channels.toggle', $t['channel'])], ['delete', route('notifications.channels.destroy', $t['channel'])],
            ['get', route('team.edit', $t['collaborator'])], ['put', route('team.update', $t['collaborator']), ['name' => 'Piraté', 'role' => 'admin']],
            ['patch', route('team.toggle', $t['admin'])], ['post', route('team.invitation', $t['collaborator'])], ['delete', route('team.destroy', $t['admin'])],
        ];

        foreach ($requests as $request) {
            [$method, $url] = $request;
            $status = $this->{$method}($url, $request[2] ?? [])->status();
            // 404 : introuvable dans l'agence ; 403 : action interdite au rôle avant même la recherche.
            $this->assertContains($status, [403, 404], strtoupper($method)." {$url} a répondu {$status}");
        }

        // Création rattachée à un client de l'autre agence : refusée.
        $this->post(route('sites.store'), ['name' => 'Intrus', 'url' => 'https://intrus.ci', 'client_id' => $t['client']->id,
            'interval_minutes' => 5, 'timeout_seconds' => 10, 'monitoring_provider' => 'internal'])->assertSessionHasErrors('client_id');

        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_admin_only_manages_their_own_team(): void
    {
        $this->actingAs($this->world['Alpha']['admin']);

        $this->get(route('team.index'))->assertSee('collab-alpha@exemple.ci')->assertDontSee('collab-beta@exemple.ci');
        // Inviter une adresse déjà utilisée dans une autre agence ne la déplace pas.
        $this->post(route('team.store'), ['name' => 'Vol', 'email' => 'COLLAB-beta@exemple.ci', 'role' => 'admin'])->assertSessionHasErrors('email');
        $this->assertSame($this->world['Beta']['agency']->id, $this->world['Beta']['collaborator']->fresh()->agency_id);
    }

    /** Empreinte de toutes les tables métier (contenu complet). */
    private function snapshot(): array
    {
        return collect(['agencies', 'users', 'clients', 'sites', 'checks', 'incidents', 'notification_channels', 'notification_preferences', 'notification_logs'])
            ->mapWithKeys(fn ($table) => [$table => md5(json_encode(DB::table($table)->orderBy('id')->get()))])
            ->all();
    }
}
