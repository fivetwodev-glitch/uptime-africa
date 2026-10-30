<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Espace administrateur général (lecture seule en phase 1).
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.access');

        return view('admin.index', [
            'agencies' => Agency::withCount(['users', 'clients', 'sites'])->orderBy('name')->paginate(30),
            'lastHeartbeat' => Cache::get(config('uptime.heartbeat.cache_key')),
        ]);
    }
}
