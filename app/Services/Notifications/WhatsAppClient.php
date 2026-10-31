<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Platform — Cloud API officielle (Meta).
 * POST https://graph.facebook.com/{version}/{phone_number_id}/messages, message de type « template »
 * (seul type autorisé hors fenêtre de 24 h). La réponse 200 signifie « accepté » ; la remise arrive par webhook.
 * Aucune simulation : sans configuration, l'envoi est refusé et journalisé comme tel.
 */
class WhatsAppClient
{
    /** Codes documentés pour lesquels un nouvel essai ultérieur est indiqué (limites, erreur inconnue). */
    private const RETRYABLE_CODES = [4, 80007, 130429, 131056, 131000];

    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('uptime.whatsapp'));
    }

    public function isConfigured(): bool
    {
        return ($this->config['enabled'] ?? false)
            && filled($this->config['phone_number_id'] ?? null)
            && filled($this->config['access_token'] ?? null);
    }

    public function templateFor(string $event): ?string
    {
        return $this->config['templates'][$event] ?? null;
    }

    /**
     * @param  list<string>  $bodyParameters  valeurs des variables {{1}}, {{2}}… du corps du modèle
     * @return string identifiant du message (wamid)
     */
    public function sendTemplate(string $to, string $template, array $bodyParameters): string
    {
        if (! $this->isConfigured()) {
            throw new DeliveryException('WhatsApp n\'est pas configuré sur le serveur (WHATSAPP_ENABLED, WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN).');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to, // format international avec « + » (sinon l'indicatif de l'expéditeur est ajouté)
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $this->config['language'] ?? 'fr'],
                'components' => $bodyParameters === [] ? [] : [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => mb_substr((string) $value, 0, 1000)], $bodyParameters),
                ]],
            ],
        ];
        if ($payload['template']['components'] === []) {
            unset($payload['template']['components']);
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $this->config['api_version'], $this->config['phone_number_id']);

        try {
            $response = Http::withToken(trim($this->config['access_token']))
                ->acceptJson()->asJson()
                ->timeout($this->config['timeout_seconds'] ?? 15)->connectTimeout(5)
                ->post($url, $payload);
        } catch (ConnectionException) {
            // Pas de nouvel essai : le message a pu être accepté avant la coupure (risque de doublon).
            throw new DeliveryException('WhatsApp injoignable ou délai dépassé : état de l\'envoi inconnu, aucun nouvel essai automatique.');
        }

        $id = $response->json('messages.0.id');
        if ($response->successful() && is_string($id) && $id !== '') {
            return $id;
        }

        throw $this->toException($response->status(), $response->json('error'));
    }

    private function toException(int $status, mixed $error): DeliveryException
    {
        $code = is_array($error) && is_numeric($error['code'] ?? null) ? (int) $error['code'] : null;
        $details = is_array($error) ? ($error['error_data']['details'] ?? $error['message'] ?? null) : null;

        $message = match ($code) {
            0, 190 => 'Jeton WhatsApp invalide ou expiré.',
            4, 80007, 130429 => 'Limite d\'envoi WhatsApp atteinte.',
            131056 => 'Trop de messages vers ce destinataire en peu de temps.',
            131026 => 'Message non distribuable (numéro sans WhatsApp ou application obsolète).',
            131047 => 'Fenêtre de 24 h expirée : un modèle approuvé est obligatoire.',
            131048 => 'Envoi restreint pour ce numéro (qualité / signalements).',
            132000 => 'Nombre de variables différent de celui du modèle.',
            132001 => 'Modèle inexistant dans cette langue ou non approuvé.',
            133010 => 'Numéro expéditeur non enregistré sur la plateforme.',
            100 => 'Paramètre refusé par l\'API WhatsApp.',
            default => "Erreur WhatsApp (HTTP {$status}).",
        };
        if (is_string($details) && $details !== '') {
            $message .= ' '.mb_substr($details, 0, 250);
        }
        if ($code !== null) {
            $message .= " [code {$code}]";
        }

        Log::warning('Envoi WhatsApp refusé', ['status' => $status, 'code' => $code]);

        return new DeliveryException($message, in_array($code, self::RETRYABLE_CODES, true) || $status >= 500, $code);
    }
}
