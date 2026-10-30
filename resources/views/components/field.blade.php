@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null, 'form' => null])
@php
    // $form : formulaire nommé (plusieurs formulaires sur une page) — sac d'erreurs et ancienne saisie dédiés.
    $id ??= $form ? $form.'_'.$name : $name;
    $bag = $errors->getBag($form ?? 'default');
    $error = $bag->first($name);
    $current = ($form === null || old('_form') === $form) ? old($name, $value) : $value;
@endphp
<div>
    <label for="{{ $id }}" class="label">{{ $label }}@if ($required) <span class="text-rose-500">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ $current }}" @endif
           @if ($required) required @endif
           @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
           {{ $attributes->merge(['class' => 'input'.($error ? ' input-error' : '')]) }}>
    @if ($error)
        <p id="{{ $id }}-error" class="error">{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
