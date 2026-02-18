# ASPRI Subscription & Billing

## Overview

ASPRI menggunakan model freemium dengan trial period dan subscription premium.

## Subscription Tiers

| Tier | Duration | Price | Features |
|------|----------|-------|----------|
| **Trial** | 30 hari | Gratis | Semua fitur premium |
| **Premium Monthly** | 1 bulan | Rp 10.000 | Semua fitur |
| **Premium Yearly** | 1 tahun | Rp 100.000 | Semua fitur + hemat 17% |

## Feature Comparison

| Feature | Trial (30 hari) | Premium |
|---------|-----------------|---------|
| Dashboard | ✅ | ✅ |
| Finance tracking | ✅ | ✅ |
| Schedule/Calendar | ✅ | ✅ |
| Notes | ✅ | ✅ |
| AI Chat | ✅ | ✅ |
| Telegram integration | ✅ | ✅ |
| Support | Community | Priority |

## User Journey

```mermaid
graph TD
    REG[User Register] --> TRIAL[Trial Period - 30 hari]
    TRIAL --> EXP{Trial Expired?}
    EXP -->|No| USE[Full Access]
    EXP -->|Yes| LIMIT[Limited Access]
    LIMIT --> PAY{Subscribe?}
    PAY -->|Yes| PREMIUM[Premium Access]
    PAY -->|No| LIMIT
    PREMIUM --> RENEW{Auto-renew?}
    RENEW -->|Yes| PREMIUM
    RENEW -->|No| CANCEL[Cancel]
    CANCEL --> LIMIT
```

## Database Schema

### subscriptions
Track user subscription status.

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('plan'); // trial, monthly, yearly
    $table->string('status'); // active, expired, cancelled
    $table->decimal('price', 10, 2)->default(0);
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamps();
    
    $table->index(['user_id', 'status']);
});
```

### subscription_plans
Available subscription plans.

```php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique(); // trial, monthly, yearly
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->integer('duration_days');
    $table->boolean('is_active')->default(true);
    $table->json('features')->nullable();
    $table->timestamps();
});
```

### payments
Payment history.

```php
Schema::create('payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->uuid('subscription_id');
    $table->decimal('amount', 10, 2);
    $table->string('payment_method')->nullable(); // bank_transfer, e-wallet
    $table->string('status'); // pending, completed, failed, refunded
    $table->string('external_id')->nullable(); // payment gateway reference
    $table->json('metadata')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    
    $table->foreign('subscription_id')
        ->references('id')
        ->on('subscriptions')
        ->cascadeOnDelete();
});
```

## Default Plans Seeder

```php
class SubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'trial',
                'name' => 'Trial',
                'description' => 'Coba semua fitur gratis selama 30 hari',
                'price' => 0,
                'duration_days' => 30,
            ],
            [
                'slug' => 'monthly',
                'name' => 'Premium Bulanan',
                'description' => 'Akses penuh semua fitur',
                'price' => 10000,
                'duration_days' => 30,
            ],
            [
                'slug' => 'yearly',
                'name' => 'Premium Tahunan',
                'description' => 'Hemat 17% dengan langganan tahunan',
                'price' => 100000,
                'duration_days' => 365,
            ],
        ];
        
        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
```

## Subscription Service

```php
namespace App\Services;

class SubscriptionService
{
    public function createTrialSubscription(User $user): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'plan' => 'trial',
            'status' => 'active',
            'price' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }
    
    public function subscribe(User $user, string $plan): Subscription
    {
        $planData = SubscriptionPlan::where('slug', $plan)->firstOrFail();
        
        // Cancel existing subscription
        $this->cancelActiveSubscription($user);
        
        return Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => 'active',
            'price' => $planData->price,
            'starts_at' => now(),
            'ends_at' => now()->addDays($planData->duration_days),
        ]);
    }
    
    public function hasActiveSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }
    
    public function isTrialActive(User $user): bool
    {
        return $user->subscriptions()
            ->where('plan', 'trial')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }
    
    public function getDaysRemaining(User $user): int
    {
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();
            
        return $subscription 
            ? now()->diffInDays($subscription->ends_at) 
            : 0;
    }
}
```

## Middleware

```php
// app/Http/Middleware/SubscriptionMiddleware.php
class SubscriptionMiddleware
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}
    
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$this->subscriptionService->hasActiveSubscription($user)) {
            // Redirect to subscription page
            return redirect()->route('subscription.index')
                ->with('warning', 'Masa trial Anda telah berakhir. Silakan berlangganan untuk melanjutkan.');
        }
        
        return $next($request);
    }
}
```

## Registration Flow

```php
// In RegisteredUserController or RegisterController
public function store(Request $request)
{
    // ... validation and user creation
    
    // Create trial subscription
    app(SubscriptionService::class)->createTrialSubscription($user);
    
    // ... login and redirect
}
```

## Admin Features

Di admin panel, tambahkan:
- View all subscriptions
- Subscription analytics (trial conversions, revenue)
- Manual subscription management
- Promo code management (CRUD, toggle active, search/filter)

## Promo Code System

### Overview

Admin dapat membuat kode promo yang dapat digunakan oleh user untuk memperpanjang masa berlaku membership. Setiap kode promo memiliki durasi perpanjangan (dalam hari), batas penggunaan, dan tanggal kadaluarsa.

### User Journey

```mermaid
graph TD
    ADMIN[Admin Buat Promo Code] --> CODE[Kode Promo Aktif]
    CODE --> USER[User Input Kode di Subscription Page]
    USER --> VALIDATE{Validasi}
    VALIDATE -->|Kode tidak ada| ERR1[Error: Kode tidak ditemukan]
    VALIDATE -->|Kode expired| ERR2[Error: Kode sudah kadaluarsa]
    VALIDATE -->|Kuota habis| ERR3[Error: Kuota sudah habis]
    VALIDATE -->|Sudah dipakai| ERR4[Error: Sudah pernah digunakan]
    VALIDATE -->|Tidak ada subscription| ERR5[Error: Belum berlangganan]
    VALIDATE -->|Valid| REDEEM[Perpanjang Subscription]
    REDEEM --> DONE[Membership Diperpanjang]
```

### Database Schema

#### promo_codes
```php
Schema::create('promo_codes', function (Blueprint $table) {
    $table->id();
    $table->string('code', 50)->unique();
    $table->text('description')->nullable();
    $table->integer('duration_days');
    $table->integer('max_usages')->default(1);
    $table->integer('usage_count')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamp('expires_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['code', 'is_active']);
    $table->index('expires_at');
});
```

#### promo_code_redemptions
```php
Schema::create('promo_code_redemptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
    $table->integer('days_added');
    $table->timestamp('previous_ends_at');
    $table->timestamp('new_ends_at');
    $table->timestamps();

    $table->unique(['promo_code_id', 'user_id']);
});
```

### Promo Code Validation

Kode promo divalidasi dengan ketentuan berikut sebelum dapat digunakan:

1. **Kode harus ada** di database
2. **Kode harus aktif** (`is_active = true`)
3. **Belum kadaluarsa** (`expires_at` belum lewat)
4. **Kuota belum habis** (`usage_count < max_usages`)
5. **User belum pernah redeem** kode yang sama (unique constraint)
6. **User harus punya active subscription** untuk diperpanjang

### API Endpoints

#### Admin (Promo Code Management)
- `GET /admin/promo-codes` — Daftar kode promo (paginated, search, filter)
- `POST /admin/promo-codes` — Buat kode promo baru
- `PUT /admin/promo-codes/{promoCode}` — Update kode promo
- `DELETE /admin/promo-codes/{promoCode}` — Hapus kode (hanya jika belum digunakan)
- `POST /admin/promo-codes/{promoCode}/toggle-active` — Toggle status aktif

#### User (Promo Code Redemption)
- `POST /subscription/redeem-promo` — Redeem kode promo

### Admin UI Features

- Tabel kode promo dengan search dan filter (aktif/expired/nonaktif)
- Dialog form untuk buat dan edit kode promo
- Auto-generate kode acak (8 karakter uppercase alfanumerik)
- Toggle aktif/nonaktif dengan satu klik
- Copy kode ke clipboard
- Hapus kode (hanya yang belum pernah digunakan)
- Pagination

### User UI Features

- Form input kode promo di halaman Subscription
- Riwayat penggunaan kode promo oleh user
- Feedback sukses/error saat redeem

### Service Layer

```php
// PromoCodeService
class PromoCodeService
{
    // Validasi kode promo
    public function validatePromoCode(string $code, User $user): array;

    // Redeem kode promo (dalam DB transaction)
    public function redeemPromoCode(string $code, User $user): PromoCodeRedemption;

    // CRUD operations
    public function createPromoCode(array $data): PromoCode;
    public function updatePromoCode(PromoCode $promoCode, array $data): PromoCode;
    public function getPromoCodes(array $filters = []): array;
}
```

### Testing

Fitur promo code memiliki 41 test cases yang mencakup:
- **Model tests**: Validitas kode, atribut, scopes
- **Service validation tests**: Semua error cases + valid flow
- **Service redemption tests**: Perpanjangan subscription, pembuatan redemption, increment usage
- **Admin HTTP tests**: View, create, duplicate prevention, update, delete, toggle active
- **User HTTP tests**: Redeem valid/invalid/expired, tanpa subscription, double redeem, guest access
- **Filter & search tests**: Filter status, search by code/description

## Payment Integration (Future)

Untuk MVP, pembayaran bisa dilakukan manual (transfer bank).
Future integration options:
- Midtrans
- Xendit
- DOKU

## API Endpoints

### Subscription
- `GET /subscription` - View current subscription
- `POST /subscription/subscribe` - Subscribe to a plan
- `POST /subscription/cancel` - Cancel subscription

### Admin
- `GET /admin/subscriptions` - List all subscriptions
- `GET /admin/subscriptions/analytics` - Subscription analytics
