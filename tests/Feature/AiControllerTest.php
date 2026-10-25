<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_vision_diagnosis_requires_sanctum_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai/vision-diagnose');

        $response->assertUnauthorized();
    }

    public function test_text_chat_requires_sanctum_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai/text-chat');

        $response->assertUnauthorized();
    }

    public function test_vision_diagnosis_sends_an_image_to_groq_and_returns_the_diagnosis(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '## Diagnosis']],
                ],
            ]),
        ]);

        $response = $this->post('/api/v1/ai/vision-diagnose', [
            'image' => UploadedFile::fake()->image('board.jpg'),
            'prompt' => 'Periksa area konektor pengisian.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'diagnosis' => '## Diagnosis',
                'model_used' => 'meta-llama/llama-4-scout-17b-16e-instruct',
            ])
            ->assertJsonStructure(['analyzed_at']);

        Http::assertSent(function (ClientRequest $request): bool {
            $content = $request['messages'][1]['content'];

            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-groq-api-key')
                && $request['model'] === 'meta-llama/llama-4-scout-17b-16e-instruct'
                && $content[0]['text'] === 'Periksa area konektor pengisian.'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/jpeg;base64,');
        });
    }

    public function test_vision_diagnosis_rejects_a_missing_image(): void
    {
        $this->actingAsTechnician();

        $response = $this->postJson('/api/v1/ai/vision-diagnose');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_vision_diagnosis_rejects_a_non_image_upload(): void
    {
        $this->actingAsTechnician();

        $response = $this->post('/api/v1/ai/vision-diagnose', [
            'image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_text_chat_rejects_invalid_messages_and_history_roles(): void
    {
        $this->actingAsTechnician();

        $response = $this->postJson('/api/v1/ai/text-chat', [
            'message' => str_repeat('x', 2001),
            'history' => [
                [
                    'role' => 'system',
                    'content' => str_repeat('x', 2001),
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'message',
                'history.0.role',
                'history.0.content',
            ]);
    }

    public function test_text_chat_sends_validated_history_and_returns_the_reply(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Berikut SOP penerimaan servis.']],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/v1/ai/text-chat', [
            'message' => 'Bagaimana menerima perangkat servis?',
            'history' => [
                ['role' => 'user', 'content' => 'Saya mengelola bengkel.'],
                ['role' => 'assistant', 'content' => 'Siap membantu SOP bengkel.'],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reply' => 'Berikut SOP penerimaan servis.',
                'model_used' => 'qwen/qwen3-8b',
            ]);

        Http::assertSent(function (ClientRequest $request): bool {
            $messages = $request['messages'];

            return $request->hasHeader('Authorization', 'Bearer test-groq-api-key')
                && $request['model'] === 'qwen/qwen3-8b'
                && $messages[0]['role'] === 'system'
                && $messages[1] === ['role' => 'user', 'content' => 'Saya mengelola bengkel.']
                && $messages[2] === ['role' => 'assistant', 'content' => 'Siap membantu SOP bengkel.']
                && $messages[3] === ['role' => 'user', 'content' => 'Bagaimana menerima perangkat servis?'];
        });
    }

    public function test_text_chat_retries_with_fallback_model_when_primary_returns_404(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fakeSequence('https://api.groq.com/openai/v1/chat/completions')
            ->push(['error' => 'Model not found'], 404)
            ->push([
                'choices' => [
                    ['message' => ['content' => 'Balasan dari model cadangan.']],
                ],
            ]);

        $response = $this->postJson('/api/v1/ai/text-chat', [
            'message' => 'Bagaimana mengatur antrean servis?',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reply' => 'Balasan dari model cadangan.',
                'model_used' => 'llama-3.3-70b-versatile',
            ]);

        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool => $request['model'] === 'qwen/qwen3-8b');
        Http::assertSent(fn (ClientRequest $request): bool => $request['model'] === 'llama-3.3-70b-versatile');
    }

    public function test_text_chat_retries_with_fallback_model_when_primary_returns_422(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fakeSequence('https://api.groq.com/openai/v1/chat/completions')
            ->push(['error' => 'Model unavailable'], 422)
            ->push([
                'choices' => [
                    ['message' => ['content' => 'Balasan dari model cadangan.']],
                ],
            ]);

        $response = $this->postJson('/api/v1/ai/text-chat', [
            'message' => 'Bagaimana menyiapkan nota garansi?',
        ]);

        $response->assertOk()
            ->assertJsonPath('model_used', 'llama-3.3-70b-versatile');

        Http::assertSentCount(2);
    }

    public function test_vision_diagnosis_returns_service_unavailable_when_groq_connection_fails(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::failedConnection(),
        ]);

        $response = $this->post('/api/v1/ai/vision-diagnose', [
            'image' => UploadedFile::fake()->image('board.png'),
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Layanan AI sedang mengalami gangguan. Silakan coba kembali beberapa saat lagi.',
            ]);
    }

    public function test_text_chat_returns_service_unavailable_when_both_models_fail(): void
    {
        $this->authenticateWithGroq();
        Http::preventStrayRequests();
        Http::fakeSequence('https://api.groq.com/openai/v1/chat/completions')
            ->push(['error' => 'Model unavailable'], 422)
            ->push(['error' => 'Upstream unavailable'], 500);

        $response = $this->postJson('/api/v1/ai/text-chat', [
            'message' => 'Bagaimana mengelola antrean servis?',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    private function actingAsTechnician(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function authenticateWithGroq(): void
    {
        $this->actingAsTechnician();
        config(['services.groq.api_key' => 'test-groq-api-key']);
    }
}
