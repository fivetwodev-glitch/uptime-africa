<?php

use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Passage de 5 rôles (owner, admin, member, viewer) à 3 rôles :
 * super administrateur, administrateur d'agence, collaborateur.
 * Les comptes existants sont rattachés au rôle équivalent puis les anciens rôles supprimés.
 */
return new class extends Migration
{
    private const MAPPING = ['owner' => 'admin', 'member' => 'collaborator', 'viewer' => 'collaborator'];

    public function up(): void
    {
        PermissionRegistry::sync();

        $roles = DB::table('roles')->pluck('id', 'slug');

        foreach (self::MAPPING as $old => $new) {
            if (isset($roles[$old])) {
                DB::table('users')->where('role_id', $roles[$old])->update(['role_id' => $roles[$new]]);
                DB::table('permission_role')->where('role_id', $roles[$old])->delete();
                DB::table('roles')->where('id', $roles[$old])->delete();
            }
        }
    }

    public function down(): void
    {
        // Fusion non réversible : les comptes restent sur les rôles conservés.
    }
};
