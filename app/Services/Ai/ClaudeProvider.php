<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClaudeProvider implements AiProviderInterface
{
    protected ?string $apiKey;

    protected string $model;

    protected string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.anthropic.api_key', '');
        $this->model = $model ?? config('services.anthropic.model', 'claude-4-5-haiku');
        $this->baseUrl = config('services.anthropic.base_url', 'https://api.anthropic.com/v1');
    }

    /**
     * Send a chat completion request.
     */
    public function chat(array $messages, array $options = []): string|array
    {
        // Separate system messages from other messages (Anthropic requires system as separate parameter)
        $systemMessage = null;
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $chatMessages[] = $message;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        // Add function/tool definitions if provided
        if (isset($options['functions'])) {
            $payload['tools'] = array_map(function ($func) {
                return [
                    'name' => $func['name'],
                    'description' => $func['description'],
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => $func['parameters']['properties'] ?? [],
                        'required' => $func['parameters']['required'] ?? [],
                    ],
                ];
            }, $options['functions']);
        }

        Log::debug('Claude API Request', ['model' => $payload['model'], 'message_count' => count($chatMessages)]);

        $response = $this->client()->post('/messages', $payload);

        if ($response->failed()) {
            Log::error('Claude API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to get response from Claude: '.$response->body());
        }

        $data = $response->json();

        // Handle tool/function calls
        if (isset($data['content'][0]['type']) && $data['content'][0]['type'] === 'tool_use') {
            $toolUse = $data['content'][0];

            return [
                'function_name' => $toolUse['name'],
                'arguments' => $toolUse['input'],
            ];
        }

        // Handle text response
        $content = '';
        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        Log::debug('Claude API Response', [
            'has_content' => ! empty($content),
            'stop_reason' => $data['stop_reason'] ?? 'unknown',
            'usage' => $data['usage'] ?? null,
        ]);

        if (empty($content)) {
            Log::warning('Claude returned empty content', ['response' => $data]);
        }

        return $content;
    }

    /**
     * Send a chat completion request with streaming response.
     */
    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $fullResponse = '';

        // Separate system messages
        $systemMessage = null;
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $chatMessages[] = $message;
            }
        }

        $httpOptions = [
            'stream' => true,
        ];

        // Disable SSL verification in local/development environment
        if (app()->environment('local', 'development')) {
            $httpOptions['verify'] = false;
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'stream' => true,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->withOptions($httpOptions)->post($this->baseUrl.'/messages', $payload);

        $body = $response->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                $json = json_decode($data, true);

                // Handle different event types
                if (isset($json['type'])) {
                    if ($json['type'] === 'content_block_delta' && isset($json['delta']['text'])) {
                        $content = $json['delta']['text'];
                        $fullResponse .= $content;
                        $callback($content);
                    }
                }
            }
        }

        return $fullResponse;
    }

    protected function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
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
