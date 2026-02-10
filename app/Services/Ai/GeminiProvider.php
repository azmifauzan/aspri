<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiProvider implements AiProviderInterface
{
    protected ?string $apiKey;

    protected string $model;

    protected string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.gemini.api_key', '');
        $this->model = $model ?? config('services.gemini.model', 'gemini-2.5-flash');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/openai');
    }

    /**
     * Send a chat completion request.
     */
    public function chat(array $messages, array $options = []): string|array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_completion_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        // Add function/tool definitions if provided
        if (isset($options['functions'])) {
            $payload['tools'] = array_map(fn ($func) => [
                'type' => 'function',
                'function' => $func,
            ], $options['functions']);
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        Log::debug('Gemini API Request', ['model' => $payload['model'], 'message_count' => count($messages)]);

        $response = $this->client()->post('/chat/completions', $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to get response from Gemini: '.$response->body());
        }

        $data = $response->json();

        // Handle function/tool calls
        if (isset($data['choices'][0]['message']['tool_calls'][0])) {
            $toolCall = $data['choices'][0]['message']['tool_calls'][0];
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true) ?? [];

            return [
                'function_name' => $functionName,
                'arguments' => $arguments,
            ];
        }

        // Handle content response
        $content = $data['choices'][0]['message']['content'] ?? '';
        $finishReason = $data['choices'][0]['finish_reason'] ?? 'unknown';

        Log::debug('Gemini API Response', [
            'has_content' => ! empty($content),
            'finish_reason' => $finishReason,
            'usage' => $data['usage'] ?? null,
        ]);

        if (empty($content)) {
            Log::warning('Gemini returned empty content', ['response' => $data]);

            // Provide fallback message based on finish reason
            if ($finishReason === 'length') {
                return 'Maaf, respons terlalu panjang. Coba tanyakan dengan lebih spesifik atau singkat.';
            }

            return 'Maaf, saya tidak bisa memberikan respons saat ini. Silakan coba lagi.';
        }

        return $content;
    }

    /**
     * Send a chat completion request with streaming response.
     */
    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $fullResponse = '';

        $httpOptions = [
            'stream' => true,
        ];

        // Disable SSL verification in local/development environment
        if (app()->environment('local', 'development')) {
            $httpOptions['verify'] = false;
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_completion_tokens' => $options['max_tokens'] ?? 1024,
            'stream' => true,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->withOptions($httpOptions)->post($this->baseUrl.'/chat/completions', $payload);

        $body = $response->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                $content = $json['choices'][0]['delta']['content'] ?? '';

                if ($content) {
                    $fullResponse .= $content;
                    $callback($content);
                }
            }
        }

        return $fullResponse;
    }

    protected function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(120);

        // Disable SSL verification in local/development/testing environment
        if (app()->environment('local', 'development', 'testing')) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }

    /**
     * Read a line from the stream.
     */
    protected function readLine($stream): string
    {
        $buffer = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $buffer .= $char;
        }

        return trim($buffer);
    }
}
