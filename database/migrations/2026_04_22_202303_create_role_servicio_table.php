<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_servicio', function (Blueprint $table) {
            $table->id();
            
            // Relación con la tabla roles
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->onDelete('cascade'); // Si se borra el rol, se limpia la relación
            
            // Relación con la tabla servicios
            $table->foreignId('servicio_id')
                  ->constrained('servicios')
                  ->onDelete('cascade'); // Si se borra el servicio, se limpia la relación

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_servicio');
    }
};