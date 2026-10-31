{{ $alert->headline }}

@if ($alert->intro){{ $alert->intro }}

@endif
@foreach ($alert->facts as $label => $value)
{{ $label }} : {{ $value }}
@endforeach
@if ($alert->actionUrl)

{{ $alert->actionUrl }}
@endif
