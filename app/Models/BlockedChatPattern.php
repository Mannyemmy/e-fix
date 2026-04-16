<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedChatPattern extends Model
{
    protected $table = 'blocked_chat_patterns';

    protected $fillable = [
        'pattern',
        'pattern_type',
        'description',
        'is_active',
        'is_regex',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_regex' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a message matches this pattern.
     */
    public function matchesMessage(string $message): bool
    {
        if ($this->is_regex) {
            return (bool) preg_match('/' . $this->pattern . '/i', $message);
        }

        // keyword or phone_number type: simple case-insensitive contains
        return stripos($message, $this->pattern) !== false;
    }
}
