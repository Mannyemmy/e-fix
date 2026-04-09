<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderType extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'provider_types';
    protected $fillable = [
        'name', 'commission', 'status','type'
    ];

    protected $casts = [
        'commission'=> 'double',
        'status'    => 'integer',
    ];
    public function scopeList($query)
    {
        return $query->orderByRaw('deleted_at IS NULL DESC, deleted_at DESC')->orderBy('created_at', 'desc');
    }
}
