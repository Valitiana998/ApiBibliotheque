<?php

namespace App\Http\Controllers\Api;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthorController
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Author::withCount('books')->get(), 'Liste des auteurs');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->validationError($validator);

        $author = Author::create($request->only('first_name', 'last_name', 'nationality'));
        return $this->success($author, 'Auteur créé', 201);
    }

    public function show(Author $author)
    {
        return $this->success($author->load('books'), 'Détails de l\'auteur');
    }

    public function update(Request $request, Author $author)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'nationality' => 'sometimes|required|string|max:255',
        ]);
        if ($validator->fails()) return $this->validationError($validator);

        $author->update($request->only('first_name', 'last_name', 'nationality'));
        return $this->success($author->load('books'), 'Auteur modifié');
    }

    public function destroy(Author $author)
    {
        $author->delete();
        return response()->json(null, 204);
    }

    private function validationError($validator)
    {
        return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
    }
}
