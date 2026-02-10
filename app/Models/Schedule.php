<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
