<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController
{
    use ApiResponse;

    public function login(Request $request, JwtService $jwt)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Identifiants invalides'], 401);
        }

        $token = $jwt->encode(['sub' => $user->id, 'email' => $user->email]);
        return $this->success(['token' => $token, 'user' => $user], 'Authentification réussie');
    }
}
