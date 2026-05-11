<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_key',
        'module_name',
        'allowed_roles',
        'allowed_user_ids',
        'enabled',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'allowed_user_ids' => 'array',
        'enabled' => 'boolean',
    ];
}

