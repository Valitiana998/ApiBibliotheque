<?php

namespace App\Http\Middleware;

use App\Models\Borrow;
use Closure;
use Illuminate\Http\Request;

class LimitFreeBorrows
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('jwt_user');
        if ($user && $user->subscription_status !== 'premium') {
            $activeBorrows = Borrow::where('user_id', $user->id)->whereNull('returned_at')->count();
            if ($activeBorrows >= 2) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'La formule gratuite est limitée à 2 emprunts actifs. Passez à Premium pour emprunter sans limite.',
                    'errors' => ['subscription' => ['Limite de 2 emprunts actifs atteinte.']],
                ], 422);
            }
        }

        return $next($request);
    }
}