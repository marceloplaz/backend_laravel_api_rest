<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuthorization extends Model
{
    // Esto es vital para que funcione el create() en el controlador
    protected $fillable = [
        'code',
        'super_admin_id',
        'action',
        'used',
        'expires_at'
    ];
}