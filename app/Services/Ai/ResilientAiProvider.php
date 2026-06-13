<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decorator around an AiProviderInterface that adds:
 *  - Per-role model override (e.g. a "fast" model for background jobs).
 *  - A single retry with a fallback model when the primary call fails.
 *
 * Streaming retries are only attempted if no chunk has been emitted yet,
 * to avoid duplicating output already sent to the caller.
 */
class ResilientAiProvider implements AiProviderInterface
{
    protected ?string $model;

    protected ?string $fallbackModel;

    public function __construct(
        protected AiProviderInterface $inner,
        ?string $model = null,
        ?string $fallbackModel = null,
    ) {
        $this->model = $model !== '' ? $model : null;
        $this->fallbackModel = $fallbackModel !== '' ? $fallbackModel : null;
    }

    /**
     * Send a chat completion request.
     */
    public function chat(array $messages, array $options = []): string|array
    {
        $resolvedOptions = $this->applyModel($options);

        try {
            return $this->inner->chat($messages, $resolvedOptions);
        } catch (Throwable $e) {
            $fallback = $this->resolveFallback($resolvedOptions['model'] ?? null);

            if ($fallback === null) {
                throw $e;
            }

            Log::warning('AI provider chat call failed, retrying with fallback model', [
                'failed_model' => $resolvedOptions['model'] ?? null,
                'fallback_model' => $fallback,
                'error' => $e->getMessage(),
            ]);

            return $this->inner->chat($messages, array_merge($resolvedOptions, ['model' => $fallback]));
        }
    }

    /**
     * Send a chat completion request with streaming response.
     */
    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $resolvedOptions = $this->applyModel($options);

        $emitted = false;
        $trackedCallback = function ($chunk) use (&$emitted, $callback) {
            $emitted = true;
            $callback($chunk);
        };

        try {
            return $this->inner->chatStream($messages, $trackedCallback, $resolvedOptions);
        } catch (Throwable $e) {
            $fallback = $this->resolveFallback($resolvedOptions['model'] ?? null);

            if ($fallback === null || $emitted) {
                throw $e;
            }

            Log::warning('AI provider chatStream call failed, retrying with fallback model', [
                'failed_model' => $resolvedOptions['model'] ?? null,
                'fallback_model' => $fallback,
                'error' => $e->getMessage(),
            ]);

            return $this->inner->chatStream($messages, $trackedCallback, array_merge($resolvedOptions, ['model' => $fallback]));
        }
    }

    /**
     * Apply the configured model override, unless the caller already
     * specified one in $options.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function applyModel(array $options): array
    {
        if ($this->model !== null && ! isset($options['model'])) {
            $options['model'] = $this->model;
        }

        return $options;
    }

    /**
     * Resolve the fallback model for a failed call.
     *
     * Returns null if there is no fallback configured, or if the fallback
     * is the same as the model that just failed (no point retrying).
     */
    protected function resolveFallback(?string $failedModel): ?string
    {
        if ($this->fallbackModel === null) {
            return null;
        }

        if ($this->fallbackModel === $failedModel) {
            return null;
        }

        return $this->fallbackModel;
    }
}
