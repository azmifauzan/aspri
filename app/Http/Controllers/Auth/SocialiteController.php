<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\Subscription\SubscriptionService;
use App\Services\Telegram\AdminNotificationService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private AdminNotificationService $adminNotificationService
    ) {}

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Gagal login dengan Google. Silakan coba lagi.');
        }

        $user = User::where('google_id', $googleUser->id)->first();
        $isNewUser = false;

        if (! $user) {
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Auto-link existing account
                $user->update([
                    'google_id' => $googleUser->id,
                    'google_avatar' => $googleUser->avatar,
                ]);
            } else {
                // Create new user — email_verified_at uses forceFill since it's not in $fillable
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'google_avatar' => $googleUser->avatar,
                    'password' => null,
                    'role' => 'user',
                    'is_active' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                // Create default profile so the app doesn't break before user fills in persona
                $user->profile()->create([
                    'call_preference' => null,
                    'aspri_name' => 'ASPRI',
                    'aspri_persona' => null,
                ]);

                // Provision free trial subscription
                $this->subscriptionService->createFreeTrial($user);

                // Send welcome notification (queued)
                $trialDays = (int) SystemSetting::getValue('free_trial_days', 30);
                $user->notify(new WelcomeNotification($trialDays));

                // Notify admins via Telegram
                $this->adminNotificationService->notifyNewUserRegistration($user);

                $isNewUser = true;
            }
        } else {
            // Update avatar if changed
            $user->update(['google_avatar' => $googleUser->avatar]);
        }

        Auth::login($user);

        if ($isNewUser) {
            return redirect()->route('profile.edit')->with('status', 'Silakan lengkapi profil Anda.');
        }

        return redirect()->intended('/dashboard');
    }
}
