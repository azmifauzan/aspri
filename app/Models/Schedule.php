<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'location',
        'is_completed',
        'is_recurring',
        'recurrence_rule',
        'is_all_day',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_completed' => 'boolean',
        'is_recurring' => 'boolean',
        'is_all_day' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
