<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    private const VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    private const TEXT_MODEL = 'qwen/qwen3-8b';

    private const FALLBACK_TEXT_MODEL = 'llama-3.3-70b-versatile';

    private const SERVICE_UNAVAILABLE_MESSAGE = 'Layanan AI sedang mengalami gangguan. Silakan coba kembali beberapa saat lagi.';

    private const VISION_SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah SmartFix Vision AI, spesialis mikrosolder dan perbaikan hardware smartphone. Analisis gambar secara cermat dan balas dalam Markdown berbahasa Indonesia. Selalu gunakan empat bagian berikut, dengan isi yang relevan berdasarkan bukti visual:

## 🔍 Identifikasi Fisik & Komponen
## ⚠️ Analisis Kerusakan Terdeteksi
## 🔧 Prosedur & Pengukuran Lanjutan
Bahas pemeriksaan multimeter, injeksi voltase, dan mikrosolder bila sesuai. Utamakan langkah aman dan jelaskan jika diagnosis dari gambar belum dapat dipastikan.
## 🛒 Estimasi Suku Cadang Terkait
Sebutkan komponen terkait dan kisaran biaya dalam Rupiah jika dapat diperkirakan; nyatakan ketidakpastian bila informasi belum cukup.
PROMPT;

    private const TEXT_SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah SmartFix Assistant, konsultan operasional dan SOP bengkel smartphone. Fokus hanya pada SOP penerimaan servis, estimasi biaya wajar, manajemen antrean, nota garansi, mitigasi risiko teknisi, dan etika komunikasi pelanggan. Tolak topik di luar operasional servis smartphone dengan sopan dan arahkan percakapan kembali ke ruang lingkup tersebut. Jangan memberikan diagnosis teknis hardware; arahkan kebutuhan diagnosis ke SmartFix Vision AI.
PROMPT;

    public function visionDiagnose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return $this->serviceUnavailableResponse();
        }

        $image = $request->file('image');
        $dataUri = 'data:'.$image->getMimeType().';base64,'.base64_encode($image->getContent());
        $prompt = $validated['prompt'] ?? 'Analisis kondisi komponen smartphone pada gambar ini.';
        $messages = [
            [
                'role' => 'system',
                'content' => self::VISION_SYSTEM_PROMPT,
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $dataUri,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->requestGroq($apiKey, self::VISION_MODEL, $messages, 75);
        } catch (ConnectionException $exception) {
            Log::error('Groq vision request failed due to a connection error.', [
                'exception' => $exception::class,
            ]);

            return $this->serviceUnavailableResponse();
        }

        if (! $response->successful()) {
            Log::error('Groq vision request returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return $this->serviceUnavailableResponse();
        }

        $diagnosis = $response->json('choices.0.message.content');

        if (! is_string($diagnosis)) {
            Log::error('Groq vision response did not contain a diagnosis.');

            return $this->serviceUnavailableResponse();
        }

        return response()->json([
            'success' => true,
            'diagnosis' => $diagnosis,
            'model_used' => self::VISION_MODEL,
            'analyzed_at' => now()->toIso8601String(),
        ]);
    }

    public function textChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return $this->serviceUnavailableResponse();
        }

        $messages = [
            [
                'role' => 'system',
                'content' => self::TEXT_SYSTEM_PROMPT,
            ],
        ];

        foreach ($validated['history'] ?? [] as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $validated['message'],
        ];

        try {
            $model = self::TEXT_MODEL;
            $response = $this->requestGroq($apiKey, $model, $messages, 35);

            if (in_array($response->status(), [404, 422], true)) {
                Log::warning('Primary Groq text model was rejected; attempting the fallback model.', [
                    'status' => $response->status(),
                ]);
                $model = self::FALLBACK_TEXT_MODEL;
                $response = $this->requestGroq($apiKey, $model, $messages, 35);
            }
        } catch (ConnectionException $exception) {
            Log::error('Groq text request failed due to a connection error.', [
                'exception' => $exception::class,
            ]);

            return $this->serviceUnavailableResponse();
        }

        if (! $response->successful()) {
            Log::error('Groq text request returned an unsuccessful response.', [
                'status' => $response->status(),
                'model' => $model,
            ]);

            return $this->serviceUnavailableResponse();
        }

        $reply = $response->json('choices.0.message.content');

        if (! is_string($reply)) {
            Log::error('Groq text response did not contain a reply.', [
                'model' => $model,
            ]);

            return $this->serviceUnavailableResponse();
        }

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'model_used' => $model,
        ]);
    }

    private function apiKey(): ?string
    {
        $apiKey = config('services.groq.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            Log::error('Groq API key is not configured.');

            return null;
        }

        return $apiKey;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function requestGroq(string $apiKey, string $model, array $messages, int $timeout): Response
    {
        return Http::withToken($apiKey)
            ->timeout($timeout)
            ->post(self::API_URL, [
                'model' => $model,
                'messages' => $messages,
            ]);
    }

    private function serviceUnavailableResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => self::SERVICE_UNAVAILABLE_MESSAGE,
        ], 503);
    }
}
