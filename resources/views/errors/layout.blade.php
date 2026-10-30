<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name', 'Uptime Africa') }}</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;padding:16px}
        .box{max-width:32rem;text-align:center}
        .code{font-size:3rem;font-weight:700;color:#047857;margin:0}
        h1{font-size:1.25rem;margin:.5rem 0}
        p{color:#475569}
        a{color:#047857}
        small{color:#94a3b8}
    </style>
</head>
<body>
<div class="box">
    <p class="code">@yield('code')</p>
    <h1>@yield('message')</h1>
    <p><a href="{{ url('/') }}">Retour à l'accueil</a></p>
    @if ($requestId = \Illuminate\Support\Facades\Context::get('request_id'))
        <small>Référence : {{ $requestId }}</small>
    @endif
</div>
</body>
</html>
