<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotService $telegramService,
    ) {}

    /**
     * Handle incoming Telegram webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Verify secret token
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $expectedSecret = config('services.telegram.webhook_secret');

        if ($expectedSecret && $secret !== $expectedSecret) {
            Log::warning('Telegram webhook unauthorized', [
                'ip' => $request->ip(),
                'secret_provided' => (bool) $secret,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $updateData = $request->all();

            Log::info('Telegram webhook received', [
                'update_id' => $updateData['update_id'] ?? null,
            ]);

            // Create Update object
            $update = new Update($updateData);

            // Process the update
            $this->telegramService->processUpdate($update);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing Telegram webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Always return 200 to prevent Telegram from retrying
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Set webhook URL (for admin use).
     */
    public function setWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $result = $this->telegramService->setWebhook($request->input('url'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Remove webhook (for admin use).
     */
    public function removeWebhook(): JsonResponse
    {
        $result = $this->telegramService->removeWebhook();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Get webhook info (for admin use).
     */
    public function webhookInfo(): JsonResponse
    {
        $result = $this->telegramService->getWebhookInfo();

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
