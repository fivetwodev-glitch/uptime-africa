<?php

namespace App\Services\Notifications;

use App\Enums\ChannelType;
use App\Enums\IncidentStatus;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Mail\AlertMail;
use App\Models\Incident;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie UNE ligne du journal. Idempotent : la ligne est « réservée » (queued → sending)
 * par une mise à jour conditionnelle ; un second worker sur la même ligne ne fait rien.
 */
class ChannelSender
{
    public function __construct(private readonly WhatsAppClient $whatsapp) {}

    /** @return bool true si un nouvel essai est pertinent */
    public function send(NotificationLog $log): bool
    {
        $claimed = NotificationLog::withoutGlobalScopes()->whereKey($log->id)
            ->where('status', NotificationStatus::Queued)
            ->update(['status' => NotificationStatus::Sending, 'attempts' => $log->attempts + 1, 'updated_at' => now()]);
        if ($claimed !== 1) {
            return false;
        }
        $log->refresh();

        if ($reason = $this->obsolete($log)) {
            $this->finish($log, NotificationStatus::Skipped, error: $reason);

            return false;
        }

        try {
            $content = MessageContent::for($log);

            [$provider, $messageId, $template] = match ($log->channel_type) {
                ChannelType::Email => ['smtp', $this->sendEmail($log, $content), null],
                ChannelType::WhatsApp => $this->sendWhatsApp($log, $content),
            };

            $this->finish($log, NotificationStatus::Sent, provider: $provider, messageId: $messageId, template: $template);

            return false;
        } catch (DeliveryException $e) {
            $retry = $e->retryable && $log->attempts < 3;
            $this->finish($log, $retry ? NotificationStatus::Queued : NotificationStatus::Failed, error: $e->getMessage());

            return $retry;
        } catch (Throwable $e) {
            // Erreur de transport email (SMTP) : nouvel essai limité.
            Log::warning('Envoi de notification en échec', ['log_id' => $log->id, 'channel' => $log->channel_type->value, 'error' => $e->getMessage()]);
            $retry = $log->channel_type === ChannelType::Email && $log->attempts < 3;
            $this->finish($log, $retry ? NotificationStatus::Queued : NotificationStatus::Failed, error: mb_substr($e->getMessage(), 0, 450));

            return $retry;
        }
    }

    private function sendEmail(NotificationLog $log, MessageContent $content): ?string
    {
        $sent = Mail::to($log->recipient)->send(new AlertMail($content));

        return $sent?->getMessageId();
    }

    /** @return array{0:string, 1:string, 2:string} */
    private function sendWhatsApp(NotificationLog $log, MessageContent $content): array
    {
        $template = $this->whatsapp->templateFor($log->event->value)
            ?? throw new DeliveryException('Aucun modèle WhatsApp configuré pour « '.$log->event->label().' ».');

        return ['whatsapp_cloud', $this->whatsapp->sendTemplate($log->recipient, $template, $content->whatsapp), $template];
    }

    /** Une alerte devenue sans objet n'est pas envoyée (ex. panne résolue pendant le délai). */
    private function obsolete(NotificationLog $log): ?string
    {
        if (! $log->incident_id) {
            return null;
        }
        $incident = Incident::withoutGlobalScopes()->find($log->incident_id);
        if ($incident === null) {
            return 'Incident supprimé.';
        }

        return match ($log->event) {
            NotificationEvent::IncidentOpened, NotificationEvent::IncidentProlonged => $incident->status === IncidentStatus::Resolved ? 'Incident résolu avant l\'envoi.' : null,
            default => null,
        };
    }

    private function finish(NotificationLog $log, NotificationStatus $status, ?string $error = null, ?string $provider = null, ?string $messageId = null, ?string $template = null): void
    {
        $log->forceFill([
            'status' => $status,
            'error_message' => $error ? mb_substr($error, 0, 500) : null,
            'provider' => $provider ?? $log->provider,
            'provider_message_id' => $messageId ? mb_substr($messageId, 0, 191) : $log->provider_message_id,
            'template' => $template ?? $log->template,
            'sent_at' => $status === NotificationStatus::Sent ? now() : $log->sent_at,
        ])->saveQuietly();
    }
}
