<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Sessions stockées en base (SESSION_DRIVER=database) : liste et révocation. */
class Sessions
{
    public static function enabled(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return Collection<int, object{id:string, ip_address:?string, user_agent:?string, last_activity:int}> */
    public static function forUser(User $user): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
    }

    public static function revokeAll(User $user, ?string $except = null): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->delete();
    }
}
