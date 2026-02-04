<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_plugin_id',
        'config_key',
        'config_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_value' => 'json',
        ];
    }

    /**
     * Get the user plugin that owns this configuration.
     */
    public function userPlugin(): BelongsTo
    {
        return $this->belongsTo(UserPlugin::class);
    }
}
