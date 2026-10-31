<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\ChannelType;
use App\Enums\RoleName;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PermissionRegistry;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DONNÉES DE TEST — développement uniquement.
 *
 * Identification :
 * - agences : is_test_data = true, nom préfixé « [TEST] », slug « test-… » ;
 * - utilisateurs : adresses @example.com (domaine réservé, aucun envoi réel) ;
 * - sites : example.com / example.org / example.net (domaines réservés IANA) ;
 * - offre : code « test », non publique, prix 0.
 *
 * Aucun contrôle ni incident n'est inventé : ils seront produits par le vrai moteur.
 * Suppression : php artisan uptime:purge-test-data
 */
class TestDataSeeder extends Seeder
{
    public const PREFIX = '[TEST] ';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('TestDataSeeder est interdit en production.');
        }

        PermissionRegistry::sync();

        $plan = Plan::updateOrCreate(['code' => 'test'], [
            'name' => self::PREFIX.'Offre de test',
            'monthly_price_xof' => 0,
            'max_sites' => 10,
            'max_users' => 5,
            'min_interval_minutes' => 5,
            'retention_days' => 14,
            'includes_whatsapp' => false,
            'is_public' => false,
            'is_active' => true,
        ]);

        $passwords = [];

        foreach (['alpha' => 'Alpha', 'beta' => 'Bêta'] as $slug => $label) {
            $agency = Agency::where('slug', "test-agence-{$slug}")->first() ?? new Agency;
            $agency->fill(['name' => self::PREFIX."Agence {$label}", 'slug' => "test-agence-{$slug}", 'contact_email' => "contact-{$slug}@example.com"]);
            $agency->is_test_data = true;
            $agency->save();

            // Toutes les écritures suivantes sont rattachées à cette agence.
            app(TenantContext::class)->set($agency->id);

            foreach ([[RoleName::Admin, 'admin'], [RoleName::Collaborator, 'collaborateur']] as [$role, $who]) {
                $email = "{$who}.{$slug}@example.com";
                $user = User::firstOrNew(['email' => $email]);

                if (! $user->exists) {
                    $passwords[$email] = Str::password(14);
                    $user->password = $passwords[$email];
                    $user->name = self::PREFIX.ucfirst($who)." {$label}";
                    $user->email_verified_at = now();
                }

                $user->agency_id = $agency->id;
                $user->assignRole($role)->save();
            }

            Subscription::firstOrCreate(
                ['agency_id' => $agency->id, 'plan_id' => $plan->id],
                ['status' => SubscriptionStatus::Trialing, 'billing_period' => BillingPeriod::Monthly, 'price_xof' => 0, 'starts_at' => now(), 'trial_ends_at' => now()->addDays(30)],
            );

            $sites = [];

            foreach (['Client 1' => ['example.com'], 'Client 2' => ['example.org', 'example.net']] as $clientName => $domains) {
                $client = Client::firstOrCreate(['name' => self::PREFIX.$clientName.' '.$label], ['email' => "client@{$domains[0]}"]);

                foreach ($domains as $domain) {
                    $sites[] = Site::firstOrCreate(
                        ['client_id' => $client->id, 'url' => "https://{$domain}"],
                        ['name' => self::PREFIX.$domain, 'interval_minutes' => 5, 'timeout_seconds' => 10],
                    );
                }
            }

            $channel = NotificationChannel::firstOrCreate(
                ['type' => ChannelType::Email, 'destination' => "alertes-{$slug}@example.com"],
                ['label' => self::PREFIX.'Email d\'alerte'],
            );

            NotificationPreference::firstOrCreate(['notification_channel_id' => $channel->id, 'site_id' => null]);

            app(TenantContext::class)->clear();
        }

        foreach ($passwords as $email => $password) {
            $this->command?->line("  {$email} / {$password}");
        }

        $this->command?->info('Données de test créées (2 agences [TEST]). Purge : php artisan uptime:purge-test-data');
    }
}
