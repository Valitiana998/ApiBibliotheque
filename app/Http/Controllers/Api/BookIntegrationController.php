<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BookIntegrationController
{
    use ApiResponse;

    public function generateSummary(Book $book, OpenAiService $openAi): JsonResponse
    {
        if ($book->summary) {
            return $this->success($this->summaryData($book), 'Résumé existant retourné (pas de nouvel appel API)');
        }

        try {
            $author = $book->author;
            $summary = $openAi->generateBookSummary(
                $book->title,
                trim(($author?->first_name ?? '').' '.($author?->last_name ?? '')),
                (int) $book->year
            );
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Impossible de générer le résumé : '.$exception->getMessage(),
            ], 503);
        }

        $book->forceFill(['summary' => $summary, 'summary_generated_at' => now()])->save();

        return $this->success($this->summaryData($book->fresh()), 'Résumé généré avec succès');
    }

    public function showSummary(Book $book): JsonResponse
    {
        if (!$book->summary) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Aucun résumé n’a encore été généré pour ce livre.',
            ], 404);
        }

        return $this->success($this->summaryData($book), 'Résumé du livre');
    }

    public function smartSearch(Request $request, OpenAiService $openAi): JsonResponse
    {
        $validator = Validator::make($request->all(), ['query' => 'required|string|min:3|max:500']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation échouée', 'errors' => $validator->errors()], 422);
        }

        $searchMode = 'ai';
        try {
            $keywords = $openAi->extractSearchKeywords($request->string('query')->toString());
        } catch (Throwable) {
            $keywords = $openAi->extractSearchKeywordsLocally($request->string('query')->toString());
            $searchMode = 'local_fallback';
        }

        if ($keywords === []) {
            return response()->json([
                'success' => true,
                'query' => $request->input('query'),
                'keywords' => [],
                'data' => [],
                'search_mode' => $searchMode,
            ]);
        }

        $books = Book::with('author')
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', '%'.$keyword.'%')
                        ->orWhere('summary', 'like', '%'.$keyword.'%')
                        ->orWhereHas('author', function ($authorQuery) use ($keyword) {
                            $authorQuery->where('first_name', 'like', '%'.$keyword.'%')
                                ->orWhere('last_name', 'like', '%'.$keyword.'%');
                        });
                }
            })
            ->get();

        return response()->json([
            'success' => true,
            'query' => $request->input('query'),
            'keywords' => $keywords,
            'data' => $books,
            'search_mode' => $searchMode,
        ]);
    }

    private function summaryData(Book $book): array
    {
        return [
            'book_id' => $book->id,
            'title' => $book->title,
            'summary' => $book->summary,
            'generated_by' => config('services.openai.model'),
            'generated_at' => $book->summary_generated_at?->toISOString(),
        ];
    }
}