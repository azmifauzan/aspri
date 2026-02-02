<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Send a chat completion request with streaming response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chatStream(array $messages, callable $callback, array $options = []): string;
}
