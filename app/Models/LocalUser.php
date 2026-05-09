<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LocalUser extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'local_users';

    protected $fillable = [
        'rostering_user_id',
        'name',
        'email',
        'role',
        'division',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];
}
