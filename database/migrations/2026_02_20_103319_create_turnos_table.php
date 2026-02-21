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
       Schema::create('turnos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre_turno'); 
    $table->time('hora_inicio');
    $table->time('hora_fin');
    $table->integer('duracion_horas');
    $table->foreignId('categoria_id')->constrained('categorias'); // Conecta con Médicos/Enfermeras
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
