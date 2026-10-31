<?php

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckController extends Controller
{
    public const PERIODS = ['24h' => 1, '7j' => 7, '30j' => 30];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'site' => ['nullable', 'string', 'max:26'],
            'resultat' => ['nullable', Rule::in(['succes', 'echec', 'inconnu'])],
            'periode' => ['nullable', Rule::in(array_keys(self::PERIODS))],
        ]);

        $days = self::PERIODS[$filters['periode'] ?? '7j'];

        $checks = Check::query()
            ->with('site:id,name,url')
            ->where('checked_at', '>=', now()->subDays($days))
            ->when($filters['site'] ?? null, fn ($q, $s) => $q->where('site_id', $s))
            ->when(($filters['resultat'] ?? null) === 'succes', fn ($q) => $q->where('is_up', true))
            ->when(($filters['resultat'] ?? null) === 'echec', fn ($q) => $q->where('is_up', false))
            ->when(($filters['resultat'] ?? null) === 'inconnu', fn ($q) => $q->whereNull('is_up'))
            ->latest('checked_at')
            ->paginate(50)
            ->withQueryString();

        return view('checks.index', [
            'checks' => $checks,
            'filters' => $filters + ['periode' => '7j'],
            'sites' => Site::orderBy('name')->get(['id', 'name']),
            'hasAnyCheck' => $checks->total() > 0 || Check::exists(),
        ]);
    }
}
