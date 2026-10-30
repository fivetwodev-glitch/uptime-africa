@extends('layouts.console')

@section('title', 'Notifications')

@php
    use App\Enums\ChannelType;
    use App\Enums\NotificationStatus;
    use App\Support\Format;
@endphp

@section('content')
<x-page-header title="Notifications" subtitle="Destinataires des alertes et historique des envois."/>

<nav class="mb-6 flex gap-1 border-b border-slate-200" aria-label="Onglets">
    @foreach (['canaux' => 'Destinataires', 'journal' => 'Journal des envois'] as $key => $label)
        <a href="{{ route('notifications.index', ['onglet' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
           class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold {{ $tab === $key ? 'border-electric-500 text-electric-600' : 'border-transparent text-slate-500 hover:text-night-900' }}">{{ $label }}</a>
    @endforeach
</nav>

@if ($tab === 'canaux')
<div class="grid gap-6 lg:grid-cols-3">
    <section class="space-y-4 lg:col-span-2">
        @forelse ($channels as $channel)
            @php $pref = $channel->preferences->first(); @endphp
            <article class="card">
                <div class="card-header">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $channel->is_active ? 'bg-electric-50 text-electric-600' : 'bg-slate-100 text-slate-400' }}">
                            <x-icon :name="$channel->type === ChannelType::Email ? 'mail' : 'phone'" class="h-4 w-4"/>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-night-900">{{ $channel->label ?: $channel->destination }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $channel->type->label() }} · {{ $channel->destination }}
                                · {{ $channel->verified_at ? 'Vérifié' : 'Non vérifié' }}
                                @unless ($channel->is_active) · <span class="font-semibold">Désactivé</span>@endunless
                            </p>
                        </div>
                    </div>
                    @can('notifications.manage')
                        <div class="flex shrink-0 gap-2">
                            <form method="POST" action="{{ route('notifications.channels.toggle', $channel) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-secondary btn-sm">{{ $channel->is_active ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                            <form method="POST" action="{{ route('notifications.channels.destroy', $channel) }}" data-confirm="Supprimer le destinataire {{ $channel->destination }} ?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm"><span class="sr-only">Supprimer</span><x-icon name="trash" class="h-4 w-4"/></button>
                            </form>
                        </div>
                    @endcan
                </div>
                @can('notifications.manage')
                    <form method="POST" action="{{ route('notifications.channels.preferences', $channel) }}" class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-end">
                        @csrf @method('PUT')
                        <fieldset class="flex flex-1 flex-wrap gap-x-6 gap-y-2 text-sm">
                            <legend class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Alerter pour tous les sites</legend>
                            <input type="hidden" name="notify_on_down" value="0">
                            <label class="flex items-center gap-2"><input type="checkbox" name="notify_on_down" value="1" class="h-4 w-4 rounded border-slate-300" @checked($pref?->notify_on_down ?? true)> Panne</label>
                            <input type="hidden" name="notify_on_recovery" value="0">
                            <label class="flex items-center gap-2"><input type="checkbox" name="notify_on_recovery" value="1" class="h-4 w-4 rounded border-slate-300" @checked($pref?->notify_on_recovery ?? true)> Rétablissement</label>
                        </fieldset>
                        <div class="w-full sm:w-40">
                            <label for="delay-{{ $channel->id }}" class="label text-xs">Délai avant alerte (min)</label>
                            <input id="delay-{{ $channel->id }}" name="delay_minutes" type="number" min="0" max="120" value="{{ $pref?->delay_minutes ?? 0 }}" class="input">
                        </div>
                        <button type="submit" class="btn-secondary">Enregistrer</button>
                    </form>
                @else
                    <p class="px-5 py-4 text-sm text-slate-500">
                        Panne : {{ ($pref?->notify_on_down ?? false) ? 'oui' : 'non' }} ·
                        Rétablissement : {{ ($pref?->notify_on_recovery ?? false) ? 'oui' : 'non' }} ·
                        Délai : {{ $pref?->delay_minutes ?? 0 }} min
                    </p>
                @endcan
            </article>
        @empty
            <div class="card">
                <x-empty-state icon="bell" title="Aucun destinataire d'alerte">
                    Ajoutez une adresse email ou un numéro WhatsApp pour être prévenu des pannes et des rétablissements.
                </x-empty-state>
            </div>
        @endforelse
    </section>

    @can('notifications.manage')
        <section class="card h-fit">
            <div class="card-header"><h2 class="card-title">Ajouter un destinataire</h2></div>
            <form method="POST" action="{{ route('notifications.channels.store') }}" class="space-y-4 p-5" novalidate>
                @csrf
                <div>
                    <label for="type" class="label">Canal</label>
                    <select id="type" name="type" class="input" data-channel-type>
                        @foreach (ChannelType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('type', 'email') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-field name="destination" label="Email ou numéro WhatsApp" required data-channel-destination maxlength="191"
                         hint="Numéro au format international : +2250701020304."/>
                <x-field name="label" label="Libellé (facultatif)" maxlength="100" placeholder="Astreinte technique"/>
                @unless ($whatsappEnabled)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">L'envoi WhatsApp n'est pas encore configuré sur ce serveur : les numéros enregistrés ne recevront rien tant qu'il ne l'est pas.</p>
                @endunless
                <button type="submit" class="btn-primary w-full">Ajouter</button>
            </form>
        </section>
    @endcan
</div>
@else
<div class="card">
    <form method="GET" action="{{ route('notifications.index') }}" class="flex gap-3 border-b border-slate-100 p-4">
        <input type="hidden" name="onglet" value="journal">
        <label class="sr-only" for="statut">Statut</label>
        <select id="statut" name="statut" class="input sm:w-64" data-autosubmit>
            <option value="">Tous les statuts</option>
            @foreach (NotificationStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['statut'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
    </form>
    @if ($logs->isEmpty())
        <x-empty-state icon="bell" title="Aucune notification envoyée">
            Chaque alerte envoyée (ou en échec) sera tracée ici avec son destinataire et son statut.
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Site</th><th>Événement</th><th>Canal</th><th>Destinataire</th><th>Statut</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($log->sent_at ?? $log->created_at) }}</td>
                        <td>@if ($log->site)<a href="{{ route('sites.show', $log->site_id) }}" class="hover:text-electric-600">{{ $log->site->name }}</a>@else — @endif</td>
                        <td>@if ($log->incident_id)<a href="{{ route('incidents.show', $log->incident_id) }}" class="link">{{ $log->event->label() }}</a>@else {{ $log->event->label() }} @endif</td>
                        <td>{{ $log->channel_type->label() }}</td>
                        <td class="max-w-[12rem] truncate">{{ $log->recipient }}</td>
                        <td>@include('notifications._status', ['log' => $log])</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
@endif
@endsection
