<?php

namespace App\Http\Controllers;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Webhook « messages » de la WhatsApp Cloud API : statuts sent / delivered / read / failed.
 * GET : vérification (hub.verify_token). POST : signature X-Hub-Signature-256 (HMAC-SHA256, secret de l'app).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('uptime.whatsapp.webhook_verify_token');

        if ($expected !== '' && $request->query('hub_mode') === 'subscribe'
            && hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('', 403);
    }

    public function receive(Request $request): Response
    {
        $secret = (string) config('uptime.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            Log::channel('security')->warning('Webhook WhatsApp : signature invalide.', ['ip' => $request->ip()]);

            return response('', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $this->applyStatus(is_array($status) ? $status : []);
                }
            }
        }

        return response('', 200);
    }

    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $new = match ($status['status'] ?? null) {
            'sent' => NotificationStatus::Sent,
            'delivered' => NotificationStatus::Delivered,
            'read', 'played' => NotificationStatus::Read,
            'failed' => NotificationStatus::Failed,
            default => null,
        };
        if (! is_string($id) || $new === null) {
            return;
        }

        $log = NotificationLog::withoutGlobalScopes()->where('provider_message_id', $id)->first();
        // Les webhooks arrivent dans le désordre : un statut ne recule jamais.
        if ($log === null || $log->status->rank() >= $new->rank()) {
            return;
        }

        $at = is_numeric($status['timestamp'] ?? null) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();
        $error = $status['errors'][0] ?? null;

        $log->forceFill([
            'status' => $new,
            'delivered_at' => $new === NotificationStatus::Delivered ? $at : $log->delivered_at,
            'read_at' => $new === NotificationStatus::Read ? $at : $log->read_at,
            'error_message' => $new === NotificationStatus::Failed && is_array($error)
                ? mb_substr(trim(($error['title'] ?? '').' '.($error['error_data']['details'] ?? '')).' [code '.($error['code'] ?? '?').']', 0, 500)
                : $log->error_message,
        ])->saveQuietly();
    }
}
