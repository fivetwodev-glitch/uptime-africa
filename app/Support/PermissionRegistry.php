<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Synchronise les rôles et permissions système depuis config/permissions.php.
 * Idempotent : utilisable dans une migration comme dans un seeder.
 */
class PermissionRegistry
{
    public static function sync(): void
    {
        $config = require config_path('permissions.php');
        $now = now();

        foreach ($config['permissions'] as $slug => [$name, $group]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $allPermissions = array_keys($config['permissions']);
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        foreach ($config['roles'] as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $role['name'], 'description' => $role['description'], 'is_system' => true, 'updated_at' => $now, 'created_at' => $now],
            );

            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $granted = self::expand($role['permissions'], $allPermissions);

            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('permission_role')->insert(array_map(
                fn ($permission) => ['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]],
                $granted,
            ));
        }
    }

    /**
     * ['*', '!admin.access'] → toutes les permissions sauf admin.access.
     *
     * @param  list<string>  $rules
     * @param  list<string>  $all
     * @return list<string>
     */
    public static function expand(array $rules, array $all): array
    {
        $granted = in_array('*', $rules, true) ? $all : [];

        foreach ($rules as $rule) {
            if ($rule === '*') {
                continue;
            }

            if (str_starts_with($rule, '!')) {
                $granted = array_diff($granted, [substr($rule, 1)]);
            } elseif (in_array($rule, $all, true)) {
                $granted[] = $rule;
            }
        }

        return array_values(array_unique($granted));
    }
}
