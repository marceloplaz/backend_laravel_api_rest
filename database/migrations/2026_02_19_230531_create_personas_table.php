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
    Schema::create('personas', function (Blueprint $table) {
        $table->id();
        $table->string("nombre_completo", 100);
        $table->string("carnet_identidad", 100)->unique();
        $table->date("fecha_nacimiento")->nullable();
        $table->string("genero", 20);
        $table->string("telefono", 22);
        $table->string("direccion", 150);
        
        $table->enum("tipo_trabajador", ['medico', 'enfermera', 'manual', 'chofer', 'administrativo']);
        $table->string("nacionalidad", 150);  
        
        $table->enum("tipo_salario", ['TGN', 'SUS', 'CONTRATO']);
        $table->string("numero_tipo_salario", 30);  

        // 1. PRIMERO CREAS LA COLUMNA (unsignedBigInteger para que coincida con el ID de users)
        $table->unsignedBigInteger('user_id');

        // 2. LUEGO CREAS LA RELACIÓN
        $table->foreign('user_id')->references("id")->on("users")->onDelete('cascade');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
