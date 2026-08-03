<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    const EVENT_REGISTER     = 'register';
    const EVENT_LOGIN        = 'login';
    const EVENT_LOGIN_FAILED = 'login_failed';

    protected $fillable = [
        'user_id', 'event', 'email', 'user_type',
        'ip_address', 'user_agent', 'source', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * withTrashed so a soft-deleted account still resolves in the log view -
     * the deleted ones are usually the interesting ones.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Joined on the address itself rather than an id, so a log row written before
     * its IP was ever resolved picks the location up automatically once it is.
     */
    public function geolocation()
    {
        return $this->belongsTo(IpGeolocation::class, 'ip_address', 'ip_address');
    }

    public function scopeEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    public function getEventLabelAttribute()
    {
        switch ($this->event) {
            case self::EVENT_REGISTER:     return 'Signup';
            case self::EVENT_LOGIN:        return 'Login';
            case self::EVENT_LOGIN_FAILED: return 'Failed login';
            default:                       return ucfirst(str_replace('_', ' ', (string) $this->event));
        }
    }
}
