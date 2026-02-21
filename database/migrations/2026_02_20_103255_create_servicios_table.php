<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Emergencias, Pediatría, Limpieza
            $table->text('descripcion')->nullable();
            $table->integer('cantidad_pacientes')->default(0); // Dato extra del diagrama
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};