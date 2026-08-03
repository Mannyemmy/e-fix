<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpGeolocation extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'ip_address', 'country', 'country_code', 'region', 'city',
        'latitude', 'longitude', 'timezone',
        'isp', 'org', 'as_name',
        'is_mobile', 'is_proxy', 'is_hosting',
        'lookup_status', 'looked_up_at',
    ];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'is_mobile'    => 'boolean',
        'is_proxy'     => 'boolean',
        'is_hosting'   => 'boolean',
        'looked_up_at' => 'datetime',
    ];

    /**
     * "Lagos, Lagos, Nigeria" - skipping any part the provider did not return.
     */
    public function getLocationLabelAttribute()
    {
        $parts = array_filter([$this->city, $this->region, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * A datacentre or proxy address behind a consumer signup is the strongest
     * single bot signal this table can give an admin.
     */
    public function getIsSuspiciousAttribute()
    {
        return $this->is_hosting || $this->is_proxy;
    }
}
