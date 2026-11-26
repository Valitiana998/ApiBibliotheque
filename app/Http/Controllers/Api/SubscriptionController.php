<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class SubscriptionController
{
    use ApiResponse;

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $user = $request->attributes->get('jwt_user');
        $secretKey = config('services.stripe.secret_key');
        if (!$secretKey) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'STRIPE_SECRET_KEY n’est pas configurée.'], 503);
        }

        try {
            $response = Http::asForm()->acceptJson()->withToken($secretKey)->timeout(20)
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => 500,
                    'currency' => 'eur',
                    'automatic_payment_methods[enabled]' => 'true',
                    'description' => 'Abonnement premium bibliothèque — 5 EUR/mois',
                    'metadata[user_id]' => (string) $user->id,
                    'metadata[plan]' => 'premium',
                ]);

            if (!$response->successful() || !$response->json('client_secret')) {
                return response()->json(['success' => false, 'data' => null, 'message' => 'Stripe n’a pas pu créer le PaymentIntent.'], 503);
            }

            return $this->success([
                'client_secret' => $response->json('client_secret'),
                'amount' => 500,
                'currency' => 'eur',
                'publishable_key' => config('services.stripe.publishable_key'),
            ], 'PaymentIntent créé');
        } catch (Throwable) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Le service Stripe est indisponible.'], 503);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->attributes->get('jwt_user');

        return $this->success([
            'subscription_status' => $user->subscription_status,
            'user_id' => $user->id,
        ], 'Statut de l’abonnement');
    }
}