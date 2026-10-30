<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        if (app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $self = "'self'";
        // En développement, Vite sert les assets depuis son propre serveur.
        $dev = Vite::isRunningHot() ? ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173 http://[::1]:5173 ws://[::1]:5173' : '';

        return implode('; ', [
            "default-src {$self}",
            "script-src {$self}{$dev}",
            "style-src {$self} 'unsafe-inline'{$dev}",
            "img-src {$self} data:",
            "font-src {$self}",
            "connect-src {$self}{$dev}",
            "frame-ancestors 'none'",
            "base-uri {$self}",
            "form-action {$self}",
        ]);
    }
}
