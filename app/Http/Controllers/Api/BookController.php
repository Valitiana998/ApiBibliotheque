<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookController
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Book::with('author');
        if ($request->filled('available')) {
            $value = filter_var($request->query('available'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) {
                return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => ['available' => ['La valeur doit être true ou false.']]], 422);
            }
            $query->where('available', $value);
        }
        $books = $query->get();
        $message = $request->boolean('available') ? 'Liste des livres disponibles' : 'Liste des livres';
        return $this->success($books, $message);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'isbn' => 'required|string|max:255|unique:books,isbn',
            'year' => 'required|integer|min:1000|max:' . date('Y'),
            'available' => 'sometimes|boolean',
            'author_id' => 'required|integer|exists:authors,id',
        ]);
        if ($validator->fails()) return $this->validationError($validator);

        $book = Book::create($request->only('title', 'isbn', 'year', 'available', 'author_id'));
        return $this->success($book->load('author'), 'Livre créé', 201);
    }

    public function show(Book $book)
    {
        return $this->success($book->load('author'), 'Détails du livre');
    }

    public function update(Request $request, Book $book)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'isbn' => 'sometimes|required|string|max:255|unique:books,isbn,' . $book->id,
            'year' => 'sometimes|required|integer|min:1000|max:' . date('Y'),
            'available' => 'sometimes|boolean',
            'author_id' => 'sometimes|required|integer|exists:authors,id',
        ]);
        if ($validator->fails()) return $this->validationError($validator);

        $book->update($request->only('title', 'isbn', 'year', 'available', 'author_id'));
        return $this->success($book->load('author'), 'Livre modifié');
    }

    public function destroy(Book $book)
    {
        $book->delete();
        return response()->json(null, 204);
    }

    private function validationError($validator)
    {
        return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
    }
}
