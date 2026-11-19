<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BorrowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentification : connexion publique avec retour d'un token JWT.
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Routes publiques en lecture seule.
    Route::apiResource('authors', AuthorController::class)->only(['index', 'show']);
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // Routes protégées : création, modification et suppression.
    Route::middleware('jwt.auth')->group(function () {
        Route::apiResource('authors', AuthorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);

        // Gestion des emprunts, avec routes personnalisées.
        Route::get('/borrows', [BorrowController::class, 'index']);
        Route::post('/borrows', [BorrowController::class, 'store']);
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
