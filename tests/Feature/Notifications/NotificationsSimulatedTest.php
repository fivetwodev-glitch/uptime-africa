<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationStatus;
use App\Enums\RoleName;
use App\Jobs\SendNotification;
use App\Mail\AlertMail;
use App\Mail\DigestMail;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\Site;
use App\Services\Notifications\ChannelSender;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationSettings;
use App\Services\UptimeRobot\SyncEngine;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Interface\InterfaceTestCase;

/**
 * SCÉNARIOS SIMULÉS — aucun email ni message WhatsApp réel.
 * Mail::fake() remplace le transport SMTP ; Http::fake() renvoie les réponses documentées
 * de la WhatsApp Cloud API (developers.facebook.com/documentation/business-messaging/whatsapp).
 */
class NotificationsSimulatedTest extends InterfaceTestCase
{
    private Agency $agency;

    private Site $site;

    /** Réponses simulées courantes (les stubs Http::fake s'empilent : on passe par un seul callback). */
    private array $monitors = [];

    private mixed $graph = null;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake(function (Request $r) {
            if (str_starts_with($r->url(), 'https://api.uptimerobot.com/v3/monitors')) {
                return Http::response(['nextLink' => null, 'data' => $this->monitors]);
            }

            return str_starts_with($r->url(), 'https://graph.facebook.com/') ? $this->graph : null;
        });
        config(['uptime.uptimerobot.api_key' => 'cle-factice', 'uptime.uptimerobot.response_times_per_run' => 0]);
        $this->travelTo(now()->setTime(10, 0));

        $this->agency = Agency::factory()->create(['name' => 'Agence Test']);
        $this->site = $this->siteFor($this->agency, ['name' => 'Boutique', 'url' => 'https://boutique.exemple.ci', 'monitoring_provider' => 'uptime_robot', 'external_monitor_id' => '500']);
    }

    private function configureWhatsApp(): void
    {
        config(['uptime.whatsapp' => array_merge(config('uptime.whatsapp'), [
            'enabled' => true, 'phone_number_id' => '106540352242922', 'access_token' => 'jeton-factice',
            'app_secret' => 'secret-app-factice', 'webhook_verify_token' => 'verif-factice',
        ])]);
    }

    private function channel(string $type, string $destination, array $pref = []): NotificationChannel
    {
        $channel = NotificationChannel::withoutGlobalScopes()->forceCreate(['agency_id' => $this->agency->id, 'type' => $type, 'destination' => $destination]);
        NotificationPreference::withoutGlobalScopes()->forceCreate($pref + ['agency_id' => $this->agency->id, 'notification_channel_id' => $channel->id, 'site_id' => null]);

        return $channel;
    }

    /** Deux passages DOWN (incident ouvert) via le vrai moteur, avec une API UptimeRobot simulée. */
    private function openIncidentThroughSync(): Incident
    {
        $this->monitors = [['id' => 500, 'url' => 'https://boutique.exemple.ci', 'status' => 'DOWN', 'lastIncident' => ['cause' => 503, 'reason' => 'Service Unavailable']]];
        app(SyncEngine::class)->run(force: true);
        $this->travel(5)->minutes();
        app(SyncEngine::class)->run(force: true);

        return Incident::withoutGlobalScopes()->sole();
    }

    private function whatsappAccepted(string $wamid = 'wamid.HBgLMjI1MDcwMTAyMDMwNBUCABEYEjAwRkE='): void
    {
        $this->graph = Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '+2250701020304', 'wa_id' => '2250701020304']],
            'messages' => [['id' => $wamid]],
        ]);
    }

    public function test_simule_panne_email_envoye_une_seule_fois_et_whatsapp_non_configure_trace(): void
    {
        $this->channel('email', 'ops@agence.ci');
        $this->channel('whatsapp', '+2250701020304');

        $incident = $this->openIncidentThroughSync();
        $this->travel(5)->minutes();
        app(SyncEngine::class)->run(force: true); // 3e échec : aucune nouvelle alerte
        app(NotificationDispatcher::class)->incidentOpened($incident); // rejeu : anti-doublon

        Mail::assertSent(AlertMail::class, 1);
        Mail::assertSent(AlertMail::class, fn (AlertMail $m) => $m->hasTo('ops@agence.ci') && str_contains($m->content->subject, 'Panne : Boutique'));
        $logs = NotificationLog::withoutGlobalScopes()->get()->keyBy(fn ($l) => $l->channel_type->value);
        $this->assertCount(2, $logs);
        $this->assertSame(NotificationStatus::Sent, $logs['email']->status);
        $this->assertSame(NotificationStatus::Skipped, $logs['whatsapp']->status);
        $this->assertStringContainsString('pas configuré', $logs['whatsapp']->error_message);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
    }

    public function test_simule_whatsapp_envoi_modele_avec_identifiant_externe(): void
    {
        $this->configureWhatsApp();
        $this->channel('whatsapp', '+2250701020304');
        $this->whatsappAccepted();

        $this->openIncidentThroughSync();

        $log = NotificationLog::withoutGlobalScopes()->sole();
        $this->assertSame(NotificationStatus::Sent, $log->status);
        $this->assertSame('wamid.HBgLMjI1MDcwMTAyMDMwNBUCABEYEjAwRkE=', $log->provider_message_id);
        $this->assertSame('uptime_africa_panne', $log->template);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/'.config('uptime.whatsapp.api_version').'/106540352242922/messages'
            && $r->hasHeader('Authorization', 'Bearer jeton-factice')
            && $r['messaging_product'] === 'whatsapp' && $r['to'] === '+2250701020304' && $r['type'] === 'template'
            && $r['template']['name'] === 'uptime_africa_panne' && $r['template']['language']['code'] === 'fr'
            && count($r['template']['components'][0]['parameters']) === 4
            && $r['template']['components'][0]['parameters'][0] === ['type' => 'text', 'text' => 'Boutique']);
    }

    public function test_simule_erreurs_whatsapp_definitives_et_temporaires(): void
    {
        $this->configureWhatsApp();
        $channel = $this->channel('whatsapp', '+2250701020304');
        $incident = Incident::factory()->create(['site_id' => $this->site->id, 'started_at' => now()]);

        $this->graph = Http::response(['error' => ['message' => '(#132001) Template name does not exist in the translation', 'type' => 'OAuthException', 'code' => 132001, 'error_data' => ['messaging_product' => 'whatsapp', 'details' => 'template name (uptime_africa_panne) does not exist in fr'], 'fbtrace_id' => 'Az8or2yhqkZfEZ-_4qDzWPo']], 404);
        app(NotificationDispatcher::class)->incidentOpened($incident);
        $log = NotificationLog::withoutGlobalScopes()->sole();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertSame(1, $log->attempts, 'erreur définitive : aucun nouvel essai');
        $this->assertStringContainsString('code 132001', $log->error_message);

        // Limite de débit (130429) : nouvel essai, 3 au plus.
        $log->delete();
        $this->graph = Http::response(['error' => ['message' => '(#130429) Rate limit hit', 'type' => 'OAuthException', 'code' => 130429, 'error_data' => ['details' => 'Cloud API message throughput has been reached.']]], 400);
        app(NotificationDispatcher::class)->incidentOpened($incident->fresh());
        $log = NotificationLog::withoutGlobalScopes()->sole();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertSame(3, $log->attempts);
        Http::assertSentCount(4);
        $this->assertSame($channel->id, $log->notification_channel_id);
    }

    public function test_simule_retour_en_ligne_seulement_si_la_panne_a_ete_annoncee(): void
    {
        $this->channel('email', 'ops@agence.ci');
        $this->channel('email', 'direction@agence.ci', ['notify_on_down' => false]);
        $incident = $this->openIncidentThroughSync();

        $this->monitors = [['id' => 500, 'url' => 'https://boutique.exemple.ci', 'status' => 'UP']];
        $this->travel(5)->minutes();
        app(SyncEngine::class)->run(force: true);

        $this->assertNotNull($incident->fresh()->resolved_at);
        Mail::assertSent(AlertMail::class, 2);
        Mail::assertSent(AlertMail::class, fn (AlertMail $m) => $m->hasTo('ops@agence.ci') && str_contains($m->content->subject, 'Retour en ligne'));
        Mail::assertNotSent(AlertMail::class, fn (AlertMail $m) => $m->hasTo('direction@agence.ci'));
    }

    public function test_simule_delai_avant_alerte_et_panne_resolue_entre_temps(): void
    {
        Queue::fake();
        $this->channel('email', 'ops@agence.ci', ['delay_minutes' => 10]);
        $incident = Incident::factory()->create(['site_id' => $this->site->id, 'started_at' => now()]);

        app(NotificationDispatcher::class)->incidentOpened($incident);
        Queue::assertPushed(SendNotification::class, fn (SendNotification $job) => $job->delay !== null);

        $incident->resolve(now()->addMinutes(3));
        app(NotificationDispatcher::class)->incidentResolved($incident->fresh());
        (new SendNotification(NotificationLog::withoutGlobalScopes()->value('id')))->handle(app(ChannelSender::class));

        Mail::assertNothingSent();
        $this->assertSame(NotificationStatus::Skipped, NotificationLog::withoutGlobalScopes()->sole()->status);
    }

    public function test_simule_parametres_agence_desactivation_et_canaux(): void
    {
        $this->channel('email', 'ops@agence.ci');
        $incident = Incident::factory()->create(['site_id' => $this->site->id, 'started_at' => now()]);

        NotificationSettings::save($this->agency->id, ['enabled' => true, 'channels' => ['email' => false, 'whatsapp' => true], 'digest' => 'off']);
        app(NotificationDispatcher::class)->incidentOpened($incident);
        NotificationSettings::save($this->agency->id, ['enabled' => false, 'channels' => ['email' => true, 'whatsapp' => true], 'digest' => 'off']);
        app(NotificationDispatcher::class)->incidentOpened($incident);

        Mail::assertNothingSent();
        $this->assertSame(0, NotificationLog::withoutGlobalScopes()->count());
    }

    public function test_simule_incident_prolonge_une_seule_alerte_selon_le_seuil(): void
    {
        $this->channel('email', 'ops@agence.ci', ['prolonged_after_minutes' => 30]);
        $this->channel('email', 'n2@agence.ci', ['prolonged_after_minutes' => 60]);
        Incident::factory()->create(['site_id' => $this->site->id, 'started_at' => now()->subMinutes(40)]);

        $this->artisan('notifications:prolonged')->assertSuccessful();
        $this->artisan('notifications:prolonged')->assertSuccessful();

        Mail::assertSent(AlertMail::class, 1);
        Mail::assertSent(AlertMail::class, fn (AlertMail $m) => $m->hasTo('ops@agence.ci') && str_contains($m->content->subject, 'Panne prolongée'));
    }

    public function test_simule_resume_periodique_envoye_une_fois_par_periode(): void
    {
        $this->channel('email', 'ops@agence.ci');
        NotificationSettings::save($this->agency->id, ['enabled' => true, 'channels' => ['email' => true, 'whatsapp' => true], 'digest' => 'daily']);

        $this->artisan('notifications:digest --force')->expectsOutputToContain('1 résumé(s)')->assertSuccessful();
        $this->artisan('notifications:digest --force')->expectsOutputToContain('0 résumé(s)')->assertSuccessful();

        Mail::assertSent(DigestMail::class, fn (DigestMail $m) => $m->hasTo('ops@agence.ci') && $m->digest['sites_count'] === 1);
        Mail::assertSent(DigestMail::class, 1);
    }

    public function test_simule_webhook_whatsapp_signature_et_statuts(): void
    {
        $this->configureWhatsApp();
        $channel = $this->channel('whatsapp', '+2250701020304');
        $log = NotificationLog::withoutGlobalScopes()->forceCreate([
            'agency_id' => $this->agency->id, 'notification_channel_id' => $channel->id, 'channel_type' => 'whatsapp',
            'recipient' => '+2250701020304', 'event' => 'test_message', 'status' => 'sent', 'provider_message_id' => 'wamid.ABC',
        ]);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verif-factice&hub.challenge=1158201444')->assertOk()->assertSee('1158201444');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=faux&hub.challenge=1')->assertForbidden();

        $post = function (string $status, int $ts, ?array $errors = null, ?string $secret = null) {
            $statusObj = ['id' => 'wamid.ABC', 'status' => $status, 'timestamp' => (string) $ts, 'recipient_id' => '2250701020304'] + ($errors ? ['errors' => $errors] : []);
            $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '102290129340398', 'changes' => [['value' => [
                'messaging_product' => 'whatsapp', 'metadata' => ['display_phone_number' => '15550783881', 'phone_number_id' => '106540352242922'],
                'statuses' => [$statusObj],
            ], 'field' => 'messages']]]]]);

            return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret ?? 'secret-app-factice'),
            ], $body);
        };

        $post('delivered', now()->timestamp, secret: 'mauvais-secret')->assertForbidden();
        $this->assertSame(NotificationStatus::Sent, $log->fresh()->status);

        $post('delivered', now()->timestamp)->assertOk();
        $post('sent', now()->timestamp)->assertOk(); // arrivé en retard : ignoré
        $this->assertSame(NotificationStatus::Delivered, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->delivered_at);

        $post('read', now()->addMinute()->timestamp)->assertOk();
        $this->assertSame(NotificationStatus::Read, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->read_at);
    }

    public function test_simule_echec_signale_par_webhook(): void
    {
        $this->configureWhatsApp();
        $channel = $this->channel('whatsapp', '+2250701020304');
        $log = NotificationLog::withoutGlobalScopes()->forceCreate([
            'agency_id' => $this->agency->id, 'notification_channel_id' => $channel->id, 'channel_type' => 'whatsapp',
            'recipient' => '+2250701020304', 'event' => 'test_message', 'status' => 'sent', 'provider_message_id' => 'wamid.F',
        ]);
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '1', 'changes' => [['field' => 'messages', 'value' => ['statuses' => [[
            'id' => 'wamid.F', 'status' => 'failed', 'timestamp' => (string) now()->timestamp, 'recipient_id' => '2250701020304',
            'errors' => [['code' => 131026, 'title' => 'Message undeliverable', 'message' => 'Message undeliverable', 'error_data' => ['details' => 'Message Undeliverable.']]],
        ]]]]]]]]);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'secret-app-factice')], $body)->assertOk();

        $this->assertSame(NotificationStatus::Failed, $log->fresh()->status);
        $this->assertStringContainsString('131026', $log->fresh()->error_message);
    }

    public function test_simule_bouton_de_test_et_droits(): void
    {
        $admin = $this->userFor($this->agency);
        $collab = $this->userFor($this->agency, RoleName::Collaborator);
        $email = $this->channel('email', 'ops@agence.ci');
        $whatsapp = $this->channel('whatsapp', '+2250701020304');

        $this->actingAs($collab)->post(route('notifications.channels.test', $email))->assertForbidden();
        $this->actingAs($collab)->put(route('notifications.settings'), ['digest' => 'daily'])->assertForbidden();

        $this->actingAs($admin)->post(route('notifications.channels.test', $email))->assertSessionHas('status');
        Mail::assertSent(AlertMail::class, fn (AlertMail $m) => $m->hasTo('ops@agence.ci') && $m->content->subject === 'Test de notification Uptime Africa');

        // WhatsApp non configuré : échec honnête, jamais « envoyé ».
        $this->actingAs($admin)->post(route('notifications.channels.test', $whatsapp))->assertSessionHas('error', fn ($m) => str_contains($m, 'pas configuré'));
        $this->assertSame(NotificationStatus::Failed, NotificationLog::withoutGlobalScopes()->where('channel_type', 'whatsapp')->sole()->status);

        $this->actingAs($admin)->put(route('notifications.settings'), ['enabled' => '1', 'email' => '1', 'whatsapp' => '0', 'digest' => 'weekly'])->assertSessionHas('status');
        $this->assertSame('weekly', NotificationSettings::for($this->agency->id)['digest']);
        $this->assertFalse(NotificationSettings::for($this->agency->id)['channels']['whatsapp']);

        $this->actingAs($admin)->get(route('notifications.index', ['onglet' => 'journal']))->assertOk()->assertSee('Message de test')->assertDontSee('jeton-factice');
    }

    public function test_simule_email_aux_couleurs_d_uptime_africa(): void
    {
        $incident = Incident::factory()->create(['site_id' => $this->site->id, 'started_at' => now(), 'last_http_status' => 503, 'last_error_message' => 'Service Unavailable']);
        $log = NotificationLog::withoutGlobalScopes()->forceCreate([
            'agency_id' => $this->agency->id, 'site_id' => $this->site->id, 'incident_id' => $incident->id, 'channel_type' => 'email',
            'recipient' => 'ops@agence.ci', 'event' => 'incident_opened',
        ]);

        $html = (new AlertMail(MessageContent::for($log)))->render();

        $this->assertStringContainsString('#0a1733', $html);
        $this->assertStringContainsString('#2f6bff', $html);
        $this->assertStringContainsString('Boutique est hors ligne', $html);
        $this->assertStringContainsString('503', $html);
        $this->assertStringContainsString(route('incidents.show', $incident->id), $html);
    }
}
