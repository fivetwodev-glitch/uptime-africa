<?php

namespace App\Services\UptimeRobot;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de l'API UptimeRobot v3 (https://uptimerobot.com/api/v3/,
 * spécification officielle https://cdn.uptimerobot.com/api/openapi.yaml).
 *
 * - Authentification : en-tête « Authorization: Bearer <clé> » (securitySchemes.bearer).
 * - Pagination de GET /monitors : `limit` (1 à 200) puis `nextLink` (null en fin de liste).
 * - Limites : HTTP 429 + en-têtes Retry-After, X-RateLimit-Remaining, X-RateLimit-Reset.
 * - Erreurs : ApiErrorResponseDto { code, message, statusCode?, error? }.
 *
 * La clé n'est lue que depuis la configuration serveur et n'est jamais journalisée.
 */
class UptimeRobotClient
{
    /** Dernières valeurs de limitation reçues (X-RateLimit-*). */
    public ?int $rateLimitRemaining = null;

    public ?int $rateLimitReset = null;

    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('uptime.uptimerobot'));
    }

    public function isConfigured(): bool
    {
        return is_string($this->config['api_key'] ?? null) && trim($this->config['api_key']) !== '';
    }

    /** GET /user/me : profil du compte, limites et abonnement (sert de test de connexion). */
    public function me(): array
    {
        return $this->send('get', '/user/me');
    }

    /** GET /monitors/{id} */
    public function getMonitor(int $id): array
    {
        return $this->send('get', "/monitors/{$id}");
    }

    /**
     * GET /monitors, toutes les pages (suivi de nextLink).
     *
     * @param  array{url?:string, name?:string, status?:string}  $filters
     * @return Generator<int, array>
     */
    public function monitors(array $filters = []): Generator
    {
        $page = $this->send('get', '/monitors', array_filter($filters + ['limit' => $this->config['page_size'] ?? 200]));

        while (true) {
            foreach ($page['data'] ?? [] as $monitor) {
                yield $monitor;
            }

            $next = $page['nextLink'] ?? null;
            if (! is_string($next) || $next === '') {
                return;
            }

            $page = $this->send('get', $this->sameHostPath($next));
        }
    }

    /**
     * POST /monitors avec le schéma CreateHttpMonitorDto
     * (champs requis : type, friendlyName, url, interval, timeout).
     *
     * @param  list<int>|null  $successCodes
     */
    public function createHttpMonitor(string $friendlyName, string $url, int $intervalSeconds, int $timeoutSeconds, ?array $successCodes = null): array
    {
        $body = [
            'type' => 'HTTP',
            'friendlyName' => mb_substr($friendlyName, 0, 250),
            'url' => $url,
            'interval' => $intervalSeconds,
            'timeout' => $timeoutSeconds,
            'httpMethodType' => 'GET',
            'followRedirections' => true,
        ];
        if ($successCodes) {
            $body['successHttpResponseCodes'] = array_map('strval', $successCodes);
        }

        // Création non idempotente : pas de nouvel essai après une erreur réseau ou 5xx
        // (le moniteur a pu être créé) ; seul un 429 est retenté.
        return $this->send('post', '/monitors', $body, safeToRetry: false);
    }

    /** PATCH /monitors/{id} (UpdateMonitorDto : champs partiels). */
    public function updateMonitor(int $id, array $fields): array
    {
        return $this->send('patch', "/monitors/{$id}", $fields);
    }

    /** POST /monitors/{id}/pause */
    public function pauseMonitor(int $id): array
    {
        return $this->send('post', "/monitors/{$id}/pause");
    }

    /** POST /monitors/{id}/start */
    public function startMonitor(int $id): array
    {
        return $this->send('post', "/monitors/{$id}/start");
    }

    /** DELETE /monitors/{id} */
    public function deleteMonitor(int $id): void
    {
        $this->send('delete', "/monitors/{$id}");
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw UptimeRobotException::notConfigured();
        }

        return Http::baseUrl($this->config['base_url'])
            ->withToken(trim($this->config['api_key']))
            ->acceptJson()
            ->asJson()
            ->timeout($this->config['timeout_seconds'] ?? 15)
            ->connectTimeout(5);
    }

    /**
     * Envoie la requête avec des nouvelles tentatives raisonnables :
     * erreur réseau, 5xx ou 429 (en respectant Retry-After), jamais sur une autre 4xx.
     */
    private function send(string $method, string $path, ?array $data = null, bool $safeToRetry = true): array
    {
        $attempts = max(1, (int) ($this->config['max_attempts'] ?? 3));

        for ($attempt = 1; ; $attempt++) {
            try {
                $request = $this->request();
                $response = match ($method) {
                    // Sans null, un tableau vide écraserait la query string de nextLink.
                    'get' => $request->get($path, $data ?: null),
                    'post' => $request->post($path, $data ?? (object) []),
                    'patch' => $request->patch($path, $data ?? []),
                    'delete' => $request->delete($path),
                };
            } catch (ConnectionException) {
                $error = new UptimeRobotException('Impossible de joindre l\'API UptimeRobot (réseau ou délai dépassé).', retryable: true);
                if ($safeToRetry && $attempt < $attempts) {
                    $this->pause(2 ** ($attempt - 1));

                    continue;
                }
                $this->logFailure($method, $path, $error);

                throw $error;
            }

            $this->rememberRateLimit($response);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $error = $this->toException($response);
            if ($error->retryable && $attempt < $attempts && ($safeToRetry || $response->status() === 429)) {
                $wait = $response->status() === 429 ? $this->retryAfter($response) : 2 ** ($attempt - 1);
                if ($wait !== null && $wait <= ($this->config['max_wait_seconds'] ?? 20)) {
                    $this->pause($wait);

                    continue;
                }
            }

            $this->logFailure($method, $path, $error);

            throw $error;
        }
    }

    private function toException(Response $response): UptimeRobotException
    {
        $status = $response->status();
        $body = $response->json();
        $apiCode = is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : null;
        $detail = is_array($body) ? ($body['message'] ?? null) : null;
        $detail = is_array($detail) ? implode(' ; ', array_filter($detail, 'is_string')) : (is_string($detail) ? $detail : null);

        $message = match (true) {
            $status === 401 => 'Clé API UptimeRobot refusée (401). Vérifiez ZITE_UPTIMEROBOT_API_KEY.',
            $status === 403 => 'Accès refusé par UptimeRobot (403) : la clé n\'a pas les droits nécessaires (clé en lecture seule ?).',
            $status === 404 => 'Moniteur introuvable chez UptimeRobot (404).',
            $status === 429 => 'Limite de requêtes UptimeRobot atteinte (429). Nouvel essai plus tard.',
            $status >= 500 => "UptimeRobot est indisponible ({$status}).",
            default => "UptimeRobot a refusé la requête ({$status}).",
        };
        if ($detail && $status < 500 && ! in_array($status, [401, 403], true)) {
            $message .= ' '.mb_substr($detail, 0, 300);
        }
        if ($apiCode) {
            $message .= " [code {$apiCode}]";
        }

        return new UptimeRobotException($message, $status, $apiCode, retryable: $status === 429 || $status >= 500);
    }

    private function rememberRateLimit(Response $response): void
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        $reset = $response->header('X-RateLimit-Reset');
        $this->rateLimitRemaining = is_numeric($remaining) ? (int) $remaining : $this->rateLimitRemaining;
        $this->rateLimitReset = is_numeric($reset) ? (int) $reset : $this->rateLimitReset;
    }

    private function retryAfter(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');
        if (is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter);
        }
        $reset = $response->header('X-RateLimit-Reset');

        return is_numeric($reset) ? max(1, (int) $reset - time()) : null;
    }

    /** nextLink est une URL complète : on n'accepte que l'hôte de l'API (pas de requête vers un tiers avec la clé). */
    private function sameHostPath(string $nextLink): string
    {
        $base = parse_url($this->config['base_url']);
        $next = parse_url($nextLink);

        if (isset($next['host']) && ($next['host'] !== ($base['host'] ?? null) || ($next['scheme'] ?? 'https') !== 'https')) {
            throw new UptimeRobotException('Lien de pagination UptimeRobot inattendu : synchronisation interrompue.');
        }

        $path = $next['path'] ?? '';
        $basePath = rtrim($base['path'] ?? '', '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return $path.(isset($next['query']) ? '?'.$next['query'] : '');
    }

    private function pause(int $seconds): void
    {
        if ($seconds > 0) {
            app()->runningUnitTests() ? null : sleep($seconds);
        }
    }

    private function logFailure(string $method, string $path, UptimeRobotException $error): void
    {
        // Jamais d'en-têtes ni de clé : uniquement la route appelée et l'erreur.
        Log::warning('Appel UptimeRobot en échec', [
            'method' => strtoupper($method),
            'path' => preg_replace('/\?.*/', '', $path),
            'status' => $error->httpStatus,
            'code' => $error->apiCode,
            'message' => $error->getMessage(),
        ]);
    }
}
