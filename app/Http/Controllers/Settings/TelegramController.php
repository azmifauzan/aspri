<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Check if user has linked Telegram account (using direct user fields)
        $isLinked = ! empty($user->telegram_chat_id);

        // Generate link code if not linked
        $linkCode = null;
        if (! $isLinked) {
            // Check for existing unexpired link code in user table
            if ($user->telegram_link_code && $user->telegram_link_expires_at && $user->telegram_link_expires_at > now()) {
                $linkCode = $user->telegram_link_code;
            } else {
                // Generate new code
                $linkCode = strtoupper(Str::random(8));
                $user->update([
                    'telegram_link_code' => $linkCode,
                    'telegram_link_expires_at' => now()->addHours(24),
                ]);
            }
        }

        return Inertia::render('settings/Telegram', [
            'botUsername' => config('services.telegram.bot_username'),
            'linkCode' => $linkCode,
            'isLinked' => $isLinked,
            'telegramUsername' => $user->telegram_username ?? null,
        ]);
    }
}
