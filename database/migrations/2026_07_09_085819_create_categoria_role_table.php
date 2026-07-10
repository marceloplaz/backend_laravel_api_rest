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
        Schema::create('categoria_role', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea hacia tu tabla de 'roles'
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            
            // Llave foránea hacia tu tabla de 'categorias' (médicos, enfermeras, etc.)
            $table->foreignId('categoria_id')->constrained()->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria_role');
    }
};