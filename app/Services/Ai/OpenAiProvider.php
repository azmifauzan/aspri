<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    protected ?string $apiKey;

    protected string $model;

    protected string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.openai.api_key', '');
        $this->model = $model ?? config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Send a chat completion request.
     */
    public function chat(array $messages, array $options = []): string
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_completion_tokens' => $options['max_tokens'] ?? 2048,
        ];

        // Only add temperature if not using a model that doesn't support it
        if (! $this->isReasoningModel()) {
            $payload['temperature'] = $options['temperature'] ?? 0.7;
        }

        Log::debug('OpenAI API Request', ['model' => $payload['model'], 'message_count' => count($messages)]);

        $response = $this->client()->post('/chat/completions', $payload);

        if ($response->failed()) {
            Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to get response from OpenAI: '.$response->body());
        }

        $data = $response->json();

        // Handle different response formats (some models use 'content', others may use 'refusal' for safety, etc.)
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Log for debugging
        Log::debug('OpenAI API Response', [
            'has_content' => ! empty($content),
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
            'usage' => $data['usage'] ?? null,
        ]);

        if (empty($content)) {
            Log::warning('OpenAI returned empty content', ['response' => $data]);
        }

        return $content;
    }

    /**
     * Check if current model is a reasoning model (o1, o3, gpt-5, etc.) that doesn't support temperature.
     */
    protected function isReasoningModel(): bool
    {
        $reasoningModels = ['o1', 'o3', 'gpt-5'];

        foreach ($reasoningModels as $prefix) {
            if (str_starts_with($this->model, $prefix)) {
                return true;
            }
        }

        return false;
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
        ];

        // Only add temperature if not using a model that doesn't support it
        if (! $this->isReasoningModel()) {
            $payload['temperature'] = $options['temperature'] ?? 0.7;
        }

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
            ->timeout(120); // Increased timeout for reasoning models

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
