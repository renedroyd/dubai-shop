<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationApiToken
{
    public function handle(Request $request, Closure $next, string $ability = 'catalog:read'): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! is_string($plainTextToken) || $plainTextToken === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = IntegrationApiToken::query()
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ! $token->isUsable()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $token->allows($ability)) {
            return response()->json(['message' => 'This token is not authorized for this resource.'], 403);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('integration_api_token', $token);

        return $next($request);
    }
}
