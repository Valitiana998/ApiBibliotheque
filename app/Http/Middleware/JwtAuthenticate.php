<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized();
        }

        try {
            $payload = (new JwtService())->decode(substr($header, 7));
            $user = User::find($payload['sub'] ?? null);
            if (!$user) {
                return $this->unauthorized();
            }
            $request->attributes->set('jwt_user', $user);
        } catch (RuntimeException) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized()
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => 'Non authentifié',
        ], 401);
    }
}
