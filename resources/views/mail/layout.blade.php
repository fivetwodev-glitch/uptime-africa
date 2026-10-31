{{-- Gabarit email aux couleurs d'Uptime Africa : bleu nuit #0a1733, bleu électrique #2f6bff. Styles en ligne (clients mail). --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f9;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f9;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
            <tr>
                <td style="background:#0a1733;padding:20px 28px;">
                    <span style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.3px;">Uptime <span style="color:#2f6bff;">Africa</span></span>
                </td>
            </tr>
            @isset($banner)
                <tr><td style="background:{{ $banner }};height:4px;line-height:4px;font-size:0;">&nbsp;</td></tr>
            @endisset
            <tr><td style="padding:28px;">{{ $slot }}</td></tr>
            <tr>
                <td style="background:#f8fafc;padding:16px 28px;font-size:12px;line-height:18px;color:#64748b;">
                    Vous recevez cet email car votre adresse est un destinataire des alertes de votre agence sur {{ config('app.name') }}.
                    Les préférences se modifient dans Notifications.
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
