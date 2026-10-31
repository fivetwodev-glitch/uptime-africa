{{-- Saisie d'un paiement hors ligne reçu : référence unique (anti-doublon), moyen, note. --}}
<div class="grid gap-3 sm:grid-cols-3">
    <div>
        <label for="reference-{{ $key }}" class="label">Référence de transaction</label>
        <input id="reference-{{ $key }}" name="reference" class="input" required maxlength="100" placeholder="ex. MP2410.1234.A">
    </div>
    <div>
        <label for="method-{{ $key }}" class="label">Moyen</label>
        <select id="method-{{ $key }}" name="method" class="input">
            @foreach (\App\Http\Controllers\Admin\SubscriptionController::METHODS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="notes-{{ $key }}" class="label">Note</label>
        <input id="notes-{{ $key }}" name="notes" class="input" maxlength="500">
    </div>
</div>
