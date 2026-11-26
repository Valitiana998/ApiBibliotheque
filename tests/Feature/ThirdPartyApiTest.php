<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Borrow;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThirdPartyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_generated_saved_and_reused_without_another_provider_call(): void
    {
        config(['services.openai.api_key' => 'test-key', 'services.openai.base_url' => 'https://ai.test/v1', 'services.openai.model' => 'test-model']);
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => 'Un résumé en français.']]]])]);
        [$user, $headers] = $this->authenticatedUser();
        $book = $this->book();

        $this->withHeaders($headers)->postJson("/api/v1/books/{$book->id}/summary")
            ->assertOk()->assertJsonPath('data.summary', 'Un résumé en français.')
            ->assertJsonPath('data.generated_by', 'test-model');

        $this->withHeaders($headers)->postJson("/api/v1/books/{$book->id}/summary")
            ->assertOk()->assertJsonPath('message', 'Résumé existant retourné (pas de nouvel appel API)');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'summary' => 'Un résumé en français.']);
        Http::assertSentCount(1);
    }

    public function test_summary_endpoint_returns_saved_summary_without_calling_provider(): void
    {
        $book = $this->book(['summary' => 'Résumé déjà enregistré.', 'summary_generated_at' => now()]);
        Http::fake();

        $this->getJson("/api/v1/books/{$book->id}/summary")
            ->assertOk()->assertJsonPath('data.summary', 'Résumé déjà enregistré.');
        Http::assertNothingSent();
    }

    public function test_smart_search_uses_extracted_keywords_to_find_books(): void
    {
        config(['services.openai.api_key' => 'test-key', 'services.openai.base_url' => 'https://ai.test/v1', 'services.openai.model' => 'test-model']);
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => '{"keywords":["Clean Code","Robert"]}']]]])]);
        $book = $this->book();

        $this->postJson('/api/v1/books/smart-search', ['query' => 'un livre sur les bonnes pratiques Java'])
            ->assertOk()->assertJsonPath('keywords.0', 'Clean Code')
            ->assertJsonPath('data.0.id', $book->id);
    }

    public function test_smart_search_falls_back_to_local_search_when_provider_rejects_the_key(): void
    {
        config(['services.openai.api_key' => 'invalid-test-key', 'services.openai.base_url' => 'https://ai.test/v1', 'services.openai.model' => 'gemini-2.0-flash']);
        Http::fake(['ai.test/*' => Http::response([['error' => ['message' => 'Please pass a valid API key']]], 400)]);
        $book = $this->book(['summary' => 'Des bonnes pratiques pour programmer du code Java.']);

        $this->postJson('/api/v1/books/smart-search', ['query' => 'livres Java bonnes pratiques'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('search_mode', 'local_fallback')
            ->assertJsonPath('data.0.id', $book->id);
    }

    public function test_payment_intent_is_created_for_the_authenticated_user(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_fake', 'services.stripe.publishable_key' => 'pk_test_fake']);
        Http::fake(['api.stripe.com/*' => Http::response(['client_secret' => 'pi_test_secret'])]);
        [$user, $headers] = $this->authenticatedUser();

        $this->withHeaders($headers)->postJson('/api/v1/subscriptions/payment-intent')
            ->assertOk()->assertJsonPath('data.client_secret', 'pi_test_secret')
            ->assertJsonPath('data.amount', 500)->assertJsonPath('data.currency', 'eur');

        Http::assertSent(fn ($request) => $request['metadata[user_id]'] === (string) $user->id && $request['metadata[plan]'] === 'premium');
    }

    public function test_subscription_status_is_returned_for_authenticated_user(): void
    {
        [$user, $headers] = $this->authenticatedUser();

        $this->withHeaders($headers)->getJson('/api/v1/subscriptions/status')
            ->assertOk()->assertJsonPath('data.subscription_status', 'free');
    }

    public function test_signed_stripe_webhook_upgrades_user_and_unsigned_webhook_is_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::create(['name' => 'Lecteur', 'email' => 'reader@example.test', 'password' => Hash::make('password')]);
        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['metadata' => ['user_id' => (string) $user->id, 'plan' => 'premium']]],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'premium']);
        $this->postJson('/api/v1/webhooks/stripe', json_decode($payload, true))
            ->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_free_user_cannot_create_a_third_active_borrow(): void
    {
        [$user, $headers] = $this->authenticatedUser();
        $book = $this->book();
        foreach ([1, 2] as $_) {
            Borrow::create([
                'user_name' => $user->name,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'borrowed_at' => now(),
                'payment_status' => 'free',
            ]);
        }

        $this->withHeaders($headers)->postJson('/api/v1/borrows', ['user_name' => $user->name, 'book_id' => $book->id])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    private function authenticatedUser(): array
    {
        $user = User::create(['name' => 'Lecteur', 'email' => uniqid('reader'). '@example.test', 'password' => Hash::make('password')]);
        $token = app(JwtService::class)->encode(['sub' => $user->id, 'email' => $user->email]);

        return [$user, ['Authorization' => 'Bearer '.$token]];
    }

    private function book(array $overrides = []): Book
    {
        $author = Author::create(['first_name' => 'Robert', 'last_name' => 'Martin', 'nationality' => 'Américaine']);

        return Book::create(array_merge([
            'title' => 'Clean Code',
            'isbn' => uniqid('isbn'),
            'year' => 2008,
            'available' => true,
            'author_id' => $author->id,
        ], $overrides));
    }
}
