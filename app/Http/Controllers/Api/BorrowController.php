<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\Borrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BorrowController
{
    use ApiResponse;

    public function index()
    {
        $borrows = Borrow::with('book.author')->whereNull('returned_at')->get();
        return $this->success($borrows, 'Liste des emprunts en cours');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_name' => 'required|string|max:255',
            'book_id' => 'required|integer|exists:books,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
        }

        $book = \App\Models\Book::find($request->book_id);
        if ($book && !$book->available) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Le livre n’est pas disponible',
                'errors' => ['book_id' => ['Le livre est déjà emprunté']],
            ], 422);
        }

        $borrow = DB::transaction(function () use ($request) {
            $book = Book::whereKey($request->book_id)->lockForUpdate()->first();
            if (!$book->available) {
                throw new \RuntimeException('Le livre n’est pas disponible');
            }
            $book->update(['available' => false]);
            return Borrow::create([
                'user_name' => $request->user_name,
                'book_id' => $book->id,
                'borrowed_at' => now(),
                'returned_at' => null,
            ]);
        });

        return $this->success($borrow->load('book.author'), 'Emprunt enregistré', 201);
    }

    public function returnBook(Borrow $borrow)
    {
        if ($borrow->returned_at) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Cet emprunt est déjà rendu'], 422);
        }

        $borrow->update(['returned_at' => now()]);
        $borrow->book()->update(['available' => true]);
        return $this->success($borrow->fresh(['book.author']), 'Livre rendu');
    }
}
