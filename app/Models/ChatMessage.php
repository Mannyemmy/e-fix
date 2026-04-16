<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'firestore_doc_id',
        'sender_id',
        'receiver_id',
        'message',
        'message_type',
        'is_flagged',
        'is_blocked',
        'blocked_reason',
        'flag_reason',
        'flagged_by',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'is_blocked' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function flaggedByUser()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function scopeList($query)
    {
        return $query->orderBy('id', 'desc');
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }
}
