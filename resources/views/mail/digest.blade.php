@php use App\Support\Format; @endphp
<x-mail-frame :title="'Résumé '.$digest['period_label']" banner="#2f6bff">
    <h1 style="margin:0 0 4px;font-size:22px;line-height:28px;color:#0a1733;">Résumé {{ $digest['period_label'] }}</h1>
    <p style="margin:0 0 20px;font-size:14px;color:#64748b;">{{ $digest['agency'] }} · du {{ Format::datetime($digest['from']) }} au {{ Format::datetime($digest['to']) }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
        <tr>
            @foreach ([['Sites', $digest['sites_count']], ['Incidents', $digest['incidents_count']], ['En panne maintenant', $digest['open_count']]] as [$label, $value])
                <td style="padding:12px;background:#f1f5ff;border-radius:8px;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#0a1733;">{{ $value }}</div>
                    <div style="font-size:12px;color:#64748b;">{{ $label }}</div>
                </td>
                @unless ($loop->last)<td style="width:8px;"></td>@endunless
            @endforeach
        </tr>
    </table>

    @if ($digest['sites'] === [])
        <p style="font-size:14px;color:#334155;">Aucun site surveillé sur la période.</p>
    @else
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;border-collapse:separate;font-size:13px;">
            <tr style="background:#f8fafc;color:#64748b;">
                <td style="padding:8px 12px;">Site</td><td style="padding:8px 12px;text-align:right;">Disponibilité</td><td style="padding:8px 12px;text-align:right;">Incidents</td>
            </tr>
            @foreach ($digest['sites'] as $row)
                <tr>
                    <td style="padding:8px 12px;border-top:1px solid #e2e8f0;color:#0f172a;font-weight:600;">{{ $row['name'] }}</td>
                    <td style="padding:8px 12px;border-top:1px solid #e2e8f0;text-align:right;">{{ Format::percent($row['uptime']) }}</td>
                    <td style="padding:8px 12px;border-top:1px solid #e2e8f0;text-align:right;">{{ $row['incidents'] }}</td>
                </tr>
            @endforeach
        </table>
        <p style="margin:8px 0 0;font-size:12px;color:#64748b;">Disponibilité calculée sur les vérifications concluantes (les vérifications « inconnues » sont exclues).</p>
    @endif
    <p style="margin:24px 0 0;">
        <a href="{{ route('dashboard') }}" style="display:inline-block;background:#2f6bff;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 20px;border-radius:8px;">Voir le tableau de bord</a>
    </p>
</x-mail-frame>
