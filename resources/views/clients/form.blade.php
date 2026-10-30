@extends('layouts.console')

@php $editing = $client->exists; @endphp

@section('title', $editing ? 'Modifier '.$client->name : 'Ajouter un client')

@section('content')
<x-page-header :title="$editing ? 'Modifier le client' : 'Ajouter un client'" :subtitle="$editing ? $client->name : null">
    <x-slot:back>
        <a href="{{ $editing ? route('clients.show', $client) : route('clients.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Retour
        </a>
    </x-slot:back>
</x-page-header>

<form method="POST" action="{{ $editing ? route('clients.update', $client) : route('clients.store') }}" class="card max-w-3xl" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="space-y-5 p-5 sm:p-6">
        <x-field name="name" label="Nom du client" :value="$client->name" required maxlength="120"/>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="contact_name" label="Nom du contact" :value="$client->contact_name" maxlength="120"/>
            <x-field name="email" label="Email" type="email" :value="$client->email" maxlength="191"/>
        </div>
        <x-field name="phone" label="Téléphone" type="tel" :value="$client->phone" maxlength="32" placeholder="+225 07 01 02 03 04"/>
        <div>
            <label for="notes" class="label">Notes internes</label>
            <textarea id="notes" name="notes" rows="4" maxlength="2000" class="input @error('notes') input-error @enderror">{{ old('notes', $client->notes) }}</textarea>
            @error('notes')<p class="error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <a href="{{ $editing ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary">Annuler</a>
        <button type="submit" class="btn-primary">{{ $editing ? 'Enregistrer' : 'Ajouter le client' }}</button>
    </div>
</form>
@endsection
