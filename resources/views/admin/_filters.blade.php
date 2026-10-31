{{-- Filtre agence + recherche (pages de liste Super Admin). --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    @isset($search)
        <div><label for="q" class="label">Recherche</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="{{ $search }}"></div>
    @endisset
    <div>
        <label for="agence" class="label">Agence</label>
        <select id="agence" name="agence" class="input">
            <option value="">Toutes</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" @selected(($filters['agence'] ?? null) === $agency->id)>{{ $agency->name }}</option>
            @endforeach
        </select>
    </div>
    {{ $slot ?? '' }}
    <button type="submit" class="btn-secondary">Filtrer</button>
</form>
