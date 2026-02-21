<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'categoria_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // 🔷 Relación con Persona
    public function persona()
    {
        return $this->hasOne(Persona::class);
    }

    // 🔷 Relación con Categoria
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    // 🔷 Relación con Roles (RBAC)
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    // 🔷 Verificación de permiso
    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })->exists();
    }
    public function servicios()
{
    return $this->belongsToMany(Servicio::class, 'usuario_servicios');
}

// 🔷 Relación con Turnos asignados (N:M)
// Ejemplo en User.php
public function turnos()
{
    return $this->belongsToMany(Turno::class, 'turnos_asignados')
                ->withTimestamps()
                ->withPivot('estado', 'fecha'); // Para acceder a estos campos fácilmente
}
}