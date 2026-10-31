@extends('layouts.console')

@php $editing = $member->exists; $self = $editing && $member->is($me); @endphp

@section('title', $editing ? 'Modifier '.$member->name : 'Inviter un utilisateur')

@section('content')
<x-page-header :title="$editing ? 'Modifier l\'utilisateur' : 'Inviter un utilisateur'" :subtitle="$editing ? $member->email : 'Il recevra un email pour choisir son mot de passe.'">
    <x-slot:back>
        <a href="{{ route('team.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Équipe
        </a>
    </x-slot:back>
</x-page-header>

<form method="POST" action="{{ $editing ? route('team.update', $member) : route('team.store') }}" class="card max-w-2xl" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="space-y-5 p-5 sm:p-6">
        <x-field name="name" label="Nom" :value="$member->name" required maxlength="120"/>
        @unless ($editing)
            <x-field name="email" label="Adresse email" type="email" required maxlength="191"/>
        @endunless
        <fieldset>
            <legend class="label">Rôle <span class="text-rose-500">*</span></legend>
            @if ($self)
                <input type="hidden" name="role" value="{{ $member->role->slug }}">
                <p class="text-sm text-slate-600">{{ $member->role->name }} — vous ne pouvez pas modifier votre propre rôle.</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($roles as $role)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-electric-500 has-[:checked]:bg-electric-50">
                            <input type="radio" name="role" value="{{ $role->value }}" class="mt-0.5" @checked(old('role', $member->role?->slug ?? 'collaborator') === $role->value)>
                            <span class="text-sm font-medium text-night-900">{{ $role->label() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('role')<p class="error">{{ $message }}</p>@enderror
        </fieldset>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <a href="{{ route('team.index') }}" class="btn-secondary">Annuler</a>
        <button type="submit" class="btn-primary">{{ $editing ? 'Enregistrer' : 'Envoyer l\'invitation' }}</button>
    </div>
</form>
@endsection
