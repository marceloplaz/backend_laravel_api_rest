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
        Schema::create('turno_comida', function (Blueprint $table) {
            $table->id();
            
            // Relación con la tabla 'turnos' (Si el turno se elimina, se borran sus asociaciones)
            $table->foreignId('turno_id')
                  ->constrained('turnos')
                  ->onDelete('cascade');

            // Relación con la tabla 'comidas' (Si la comida se elimina, se borran sus asociaciones)
            $table->foreignId('comida_id')
                  ->constrained('comidas')
                  ->onDelete('cascade');

            $table->timestamps();

            // Evita duplicados: asegura que no se pueda asignar la misma comida 2 veces al mismo turno
            $table->unique(['turno_id', 'comida_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turno_comida');
    }
};