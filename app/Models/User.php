<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    // 🔷 Relación estructural con Roles (Muchos a Muchos)
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    // 🔷 Relación con Persona (La tabla personas tiene el user_id)
    public function persona(): HasOne
    {
        return $this->hasOne(Persona::class, 'user_id');
    }

   
    // 🔷 Relación con Categoria
 
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    // 🔷 Verificación de Roles
    public function hasRole(string $role): bool
    {
        return $this->roles->where('name', $role)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
{
    // 1. Verificar si el string coincide directamente con el nombre de uno de sus ROLES
    if ($this->hasRole($permission)) {
        return true;
    }

    // 2. Si no es un rol, verificar si es un PERMISO técnico en la columna 'action'
    return $this->roles->flatMap->permissions->pluck('action')->contains($permission);
}

    // 🔷 Relación con Servicios
    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'usuario_servicios', 'usuario_id', 'servicio_id');
    }

    // 🔷 Relación con Turnos
    public function turnos()
    {
       return $this->belongsToMany(Turno::class, 'turnos_asignados', 'usuario_id', 'turno_id')
                ->withPivot('fecha', 'estado')
                ->withTimestamps();
    }
    public function turnosAsignados() {
    return $this->hasMany(TurnoAsignado::class, 'usuario_id');
}
}