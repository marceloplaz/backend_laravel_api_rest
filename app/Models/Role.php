<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'descripcion'
    ];


    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function servicios()
{
    return $this->belongsToMany(Servicio::class, 'role_servicio');
}

    public function categorias()
{
    return $this->belongsToMany(Categoria::class, 'categoria_role');
}
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
    public function categoria()
     {
       return $this->belongsToMany(Categoria::class, 'categoria_role');
    }

}