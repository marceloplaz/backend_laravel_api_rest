<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    use HasFactory;

    // Ajustamos a los nombres reales de tu tabla en HeidiSQL
   protected $fillable = [
    'action',
    'subject',
    'visibleInMenu',
    'label'
];

    public function roles()
    {
        // Especificamos la tabla pivot para mayor seguridad
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}