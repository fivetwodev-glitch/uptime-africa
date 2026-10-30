<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime toutes les données marquées comme données de test
 * (agences is_test_data et tout ce qui en dépend, offre « test »).
 */
class PurgeTestData extends Command
{
    protected $signature = 'uptime:purge-test-data {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprime les données de test (agences [TEST] et leurs dépendances)';

    public function handle(): int
    {
        $count = Agency::testData()->count();

        if ($count === 0) {
            $this->info('Aucune donnée de test.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Supprimer {$count} agence(s) de test et toutes leurs données ?")) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            // Les clés étrangères en cascade suppriment utilisateurs, clients, sites, contrôles, incidents…
            Agency::testData()->get()->each->delete();
            Plan::where('code', 'test')->whereDoesntHave('subscriptions')->delete();
        });

        $this->info("{$count} agence(s) de test supprimée(s).");

        return self::SUCCESS;
    }
}
