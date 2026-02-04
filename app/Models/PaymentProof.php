<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaymentProof extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentProofFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_type',
        'amount',
        'transfer_proof_path',
        'bank_name',
        'account_name',
        'transfer_date',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $appends = ['transfer_proof_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transfer_date' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp '.number_format($this->amount, 0, ',', '.');
    }

    public function getTransferProofUrlAttribute(): ?string
    {
        if (! $this->transfer_proof_path) {
            return null;
        }

        return Storage::disk('public')->url($this->transfer_proof_path);
    }
}
