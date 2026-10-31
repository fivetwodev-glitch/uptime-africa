@extends('layouts.admin')

@section('title', 'Journal')

@section('content')
<x-page-header title="Journal des actions" subtitle="Actions sensibles de toutes les agences et de l'administration."/>
@component('admin._filters', ['agencies' => $agencies->map(fn ($name, $id) => (object) ['id' => $id, 'name' => $name]), 'filters' => $filters])
    <div><label for="action" class="label">Action (préfixe)</label><input id="action" name="action" value="{{ $filters['action'] ?? '' }}" class="input" placeholder="subscription."></div>
@endcomponent

<div class="card">
    @if ($logs->isEmpty())
        <x-empty-state icon="shield" title="Aucune action enregistrée"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Action</th><th>Auteur</th><th>Agence</th><th>Objet</th><th>Détails</th><th>IP</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::datetime($log->created_at) }}</td>
                        <td class="font-mono text-xs text-night-900">{{ $log->action }}</td>
                        <td class="text-slate-500">{{ $users[$log->user_id] ?? 'Système' }}</td>
                        <td>{{ $agencies[$log->agency_id] ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="max-w-md break-all font-mono text-[11px] text-slate-500">{{ $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                        <td class="text-xs text-slate-500">{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
@endsection
