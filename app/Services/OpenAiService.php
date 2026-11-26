<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiService
{
    public function generateBookSummary(string $title, string $author, int $year): string
    {
        $content = $this->chat([
            ['role' => 'system', 'content' => 'Tu es un expert en littérature. Génère des résumés concis et accrocheurs pour des fiches de bibliothèque. Réponds toujours en français, en 3 phrases maximum.'],
            ['role' => 'user', 'content' => "Génère un résumé pour le livre intitulé \"{$title}\", écrit par {$author}, publié en {$year}."],
        ]);

        return $content;
    }

    /** @return array<int, string> */
    public function extractSearchKeywords(string $query): array
    {
        $content = $this->chat([
            ['role' => 'system', 'content' => 'Extrais de la demande les mots-clés utiles à une recherche de livres. Réponds uniquement en JSON sous la forme {"keywords":["mot1","mot2"]}, avec 1 à 8 mots-clés en français ou dans la langue de la demande.'],
            ['role' => 'user', 'content' => $query],
        ]);

        $decoded = json_decode($content, true);
        $keywords = is_array($decoded) ? ($decoded['keywords'] ?? []) : [];
        if (!is_array($keywords)) {
            throw new RuntimeException('Réponse de recherche invalide');
        }

        return array_values(array_filter(array_slice($keywords, 0, 8), fn ($word) => is_string($word) && trim($word) !== ''));
    }

    /** @return array<int, string> */
    public function extractSearchKeywordsLocally(string $query): array
    {
        $stopWords = [
            'alors', 'avec', 'dans', 'depuis', 'des', 'donc', 'elle', 'elles', 'est', 'et', 'etre', 'être',
            'livre', 'livres', 'mais', 'mon', 'nous', 'par', 'pas', 'pour', 'qui', 'que', 'quel', 'quelle',
            'quels', 'quelles', 'sur', 'son', 'sont', 'sous', 'une', 'vers', 'votre', 'vous', 'les', 'aux',
            'the', 'and', 'are', 'for', 'from', 'in', 'is', 'of', 'on', 'or', 'to',
        ];
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = [];

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') >= 3 && !in_array($word, $stopWords, true)) {
                $keywords[$word] = $word;
            }
        }

        return array_slice(array_values($keywords), 0, 8);
    }

    private function chat(array $messages): string
    {
        $apiKey = config('services.openai.api_key');
        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY n’est pas configurée.');
        }

        $model = trim((string) config('services.openai.model'));
        if ($model === '') {
            throw new RuntimeException('OPENAI_MODEL n’est pas configuré.');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');
        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(20)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
            ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            if (is_array($errorBody) && array_is_list($errorBody)) {
                $errorBody = $errorBody[0] ?? [];
            }

            $providerMessage = data_get($errorBody, 'error.message')
                ?? data_get($errorBody, 'message')
                ?? data_get($errorBody, 'error');
            $detail = is_string($providerMessage) && $providerMessage !== ''
                ? $providerMessage
                : 'Le fournisseur a rejeté la requête.';

            if ($response->status() === 400 && preg_match('/model.*(invalid|not found|does not exist)|invalid model/i', $detail)) {
                throw new RuntimeException('Le modèle « '.$model.' » est refusé par ce fournisseur. Vérifiez OPENAI_MODEL et OPENAI_BASE_URL dans .env.');
            }

            throw new RuntimeException('Le fournisseur IA a répondu HTTP '.$response->status().' : '.$detail);
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Le service OpenAI a retourné une réponse vide.');
        }

        return trim($content);
    }
}