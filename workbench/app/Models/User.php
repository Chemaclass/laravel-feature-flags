<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /** @var array<int, string> */
    protected $fillable = ['name', 'email', 'password', 'tenant_id'];

    /** @var array<int, string> */
    protected $hidden = ['password', 'remember_token'];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
