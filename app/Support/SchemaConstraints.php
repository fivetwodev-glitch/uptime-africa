<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contraintes CHECK : appliquées sur MySQL / MariaDB (production).
 * SQLite (développement) ne permet pas de les ajouter après création :
 * elles y sont garanties par les modèles et vérifiées par les tests.
 */
class SchemaConstraints
{
    public static function check(string $table, string $name, string $expression): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("alter table `{$table}` add constraint `{$name}` check ({$expression})");
    }
}
