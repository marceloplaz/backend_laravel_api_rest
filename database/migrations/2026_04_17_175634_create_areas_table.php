<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servicio_id')
                  ->constrained('servicios')
                  ->onUpdate('cascade')
                  ->onDelete('restrict'); 

            $table->string('nombre'); 
            $table->string('numero_consultorio')->nullable(); 
            $table->boolean('activo')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};