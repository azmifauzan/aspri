<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'birth_day',
        'birth_month',
        'call_preference',
        'aspri_name',
        'aspri_persona',
        'timezone',
        'locale',
        'theme',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
