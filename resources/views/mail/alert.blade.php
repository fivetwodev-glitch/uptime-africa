@php
    $colors = ['danger' => '#e11d48', 'success' => '#059669', 'warning' => '#d97706', 'info' => '#2f6bff'];
    $color = $colors[$alert->tone] ?? '#2f6bff';
@endphp
<x-mail-frame :title="$alert->subject" :banner="$color">
    <h1 style="margin:0 0 8px;font-size:22px;line-height:28px;color:#0a1733;">{{ $alert->headline }}</h1>
    @if ($alert->intro)
        <p style="margin:0 0 20px;font-size:15px;line-height:22px;color:#334155;">{{ $alert->intro }}</p>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;border-collapse:separate;">
        @foreach ($alert->facts as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#64748b;width:38%;{{ $loop->last ? '' : 'border-bottom:1px solid #e2e8f0;' }}">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#0f172a;font-weight:600;word-break:break-word;{{ $loop->last ? '' : 'border-bottom:1px solid #e2e8f0;' }}">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    @if ($alert->actionUrl)
        <p style="margin:24px 0 0;">
            <a href="{{ $alert->actionUrl }}" style="display:inline-block;background:#2f6bff;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 20px;border-radius:8px;">Ouvrir dans Uptime Africa</a>
        </p>
    @endif
</x-mail-frame>
