<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plugin_id',
        'rating',
        'review',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Get the user that created the rating.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plugin being rated.
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
