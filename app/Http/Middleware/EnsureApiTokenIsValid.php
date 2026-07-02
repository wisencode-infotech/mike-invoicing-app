<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\ApiTokenGenerator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless bearer-token auth for /api/v1/* — resolves the requesting user
 * from an api_tokens row, never a session. Auth::setUser() (rather than
 * $request->setUserResolver()) is required here: Gate/Policy checks
 * ($this->authorize() in controllers) resolve the current user via
 * Auth::guard()->user(), not via the request instance directly — see
 * Illuminate\Auth\AuthServiceProvider::registerRequestRebindHandler(),
 * which wires $request->user() through the same auth resolver. Using only
 * setUserResolver() would make $request->user() work but leave
 * $this->authorize() always resolving no user (silently 500ing instead of
 * following ownership rules).
 */
class EnsureApiTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $this->unauthorized('Missing bearer token.');
        }

        $apiToken = ApiToken::active()
            ->where('token_hash', ApiTokenGenerator::hash($plainTextToken))
            ->first();

        if (! $apiToken) {
            return $this->unauthorized('Invalid or revoked API token.');
        }

        $apiToken->update(['last_used_at' => now()]);

        Auth::setUser($apiToken->user);

        return $next($request);
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 401);
    }
}
