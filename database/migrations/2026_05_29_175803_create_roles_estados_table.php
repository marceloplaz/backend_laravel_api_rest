<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles_estados', function (Blueprint $table) {
            $table->id();
            
            // Llaves foráneas o columnas de referencia
            $table->unsignedInteger('servicio_id');
            $table->unsignedInteger('mes_id');
            $table->unsignedInteger('gestion_id');
            
            // Campo de bloqueo: 0 = Abierto (por defecto), 1 = Bloqueado
            $table->tinyInteger('bloqueado')->default(0);
            
            $table->timestamps();

            // 🌟 SEGURO DE VIDA: Clave única compuesta.
            // Esto evita que por un error en el frontend o doble clic se inserte 
            // más de un registro para el mismo servicio, mes y gestión.
            $table->unique(['servicio_id', 'mes_id', 'gestion_id'], 'servicio_mes_gestion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles_estados');
    }
};