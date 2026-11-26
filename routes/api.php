<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BorrowController;
use App\Http\Controllers\Api\BookIntegrationController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentification : connexion publique avec retour d'un token JWT.
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Les routes statiques doivent précéder /books/{book}.
    Route::get('/books/{book}/summary', [BookIntegrationController::class, 'showSummary']);
    Route::post('/books/smart-search', [BookIntegrationController::class, 'smartSearch']);
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

    // Routes publiques en lecture seule.
    Route::apiResource('authors', AuthorController::class)->only(['index', 'show']);
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // Routes protégées : création, modification et suppression.
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/books/{book}/summary', [BookIntegrationController::class, 'generateSummary']);
        Route::post('/subscriptions/payment-intent', [SubscriptionController::class, 'createPaymentIntent']);
        Route::get('/subscriptions/status', [SubscriptionController::class, 'status']);

        Route::apiResource('authors', AuthorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);

        // Gestion des emprunts, avec routes personnalisées.
        Route::get('/borrows', [BorrowController::class, 'index']);
        Route::post('/borrows', [BorrowController::class, 'store'])->middleware('subscription.limit');
        Route::patch('/borrows/{borrow}/return', [BorrowController::class, 'returnBook']);
    });
});

Route::fallback(function (Request $request) {
    return response()->json([
        'success' => false,
        'data' => null,
        'message' => 'Endpoint introuvable',
    ], 404);
});
