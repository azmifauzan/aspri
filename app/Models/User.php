<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'telegram_chat_id',
        'telegram_username',
        'telegram_link_code',
        'telegram_link_expires_at',
        'daily_chat_count',
        'chat_count_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'telegram_link_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'chat_count_date' => 'date',
            'daily_chat_count' => 'integer',
        ];
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function financeAccounts(): HasMany
    {
        return $this->hasMany(FinanceAccount::class);
    }

    public function financeCategories(): HasMany
    {
        return $this->hasMany(FinanceCategory::class);
    }

    public function financeTransactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function promoCodeRedemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function chatUsageLogs(): HasMany
    {
        return $this->hasMany(ChatUsageLog::class);
    }

    public function plugins()
    {
        return $this->belongsToMany(Plugin::class, 'user_plugins')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->active()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Check if user is on free trial.
     */
    public function isOnFreeTrial(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription && $subscription->isFreeTrial();
    }

    /**
     * Check if user is a paid member.
     */
    public function isPaidMember(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription && $subscription->isPaid();
    }

    /**
     * Get daily chat limit based on subscription.
     */
    public function getDailyChatLimit(): int
    {
        if ($this->isPaidMember()) {
            return (int) SystemSetting::getValue('full_member_daily_chat_limit', 500);
        }

        return (int) SystemSetting::getValue('free_trial_daily_chat_limit', 50);
    }

    /**
     * Check if user has reached daily chat limit.
     */
    public function hasReachedChatLimit(): bool
    {
        $todayCount = ChatUsageLog::getTodayCount($this->id);

        return $todayCount >= $this->getDailyChatLimit();
    }

    /**
     * Get remaining chats for today.
     */
    public function getRemainingChats(): int
    {
        $todayCount = ChatUsageLog::getTodayCount($this->id);

        return max(0, $this->getDailyChatLimit() - $todayCount);
    }

    /**
     * Get subscription status info for display.
     *
     * @return array<string, mixed>
     */
    public function getSubscriptionInfo(): array
    {
        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return [
                'status' => 'none',
                'plan' => null,
                'ends_at' => null,
                'days_remaining' => 0,
                'is_paid' => false,
            ];
        }

        return [
            'status' => $subscription->status,
            'plan' => $subscription->plan,
            'ends_at' => $subscription->ends_at,
            'days_remaining' => $subscription->days_remaining,
            'is_paid' => $subscription->isPaid(),
        ];
    }
}
