<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StripeWebhookController
{
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $header = (string) $request->header('Stripe-Signature', '');
        if (!$secret || !$this->signatureIsValid($request->getContent(), $header, $secret)) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Signature Stripe invalide ou absente'], 401);
        }

        $event = json_decode($request->getContent(), true);
        if (!is_array($event) || !isset($event['type'])) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Événement Stripe invalide'], 422);
        }

        if ($event['type'] === 'payment_intent.succeeded') {
            $paymentIntent = $event['data']['object'] ?? [];
            $metadata = $paymentIntent['metadata'] ?? [];
            if (($metadata['plan'] ?? null) === 'premium' && isset($metadata['user_id'])) {
                DB::transaction(function () use ($metadata): void {
                    User::whereKey($metadata['user_id'])->update(['subscription_status' => 'premium']);
                });
            }
        }

        return response()->json(['received' => true]);
    }

    private function signatureIsValid(string $payload, string $header, string $secret): bool
    {
        if (!preg_match('/(?:^|,)t=(\d+)/', $header, $timestampMatch)) {
            return false;
        }
        preg_match_all('/(?:^|,)v1=([a-f0-9]+)/i', $header, $signatureMatches);
        $timestamp = (int) $timestampMatch[1];
        if (!$signatureMatches[1] || abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatureMatches[1] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}