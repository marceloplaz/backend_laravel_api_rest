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
        Schema::table('semanas', function (Blueprint $table) {
            
            $table->unsignedBigInteger('categoria_id')->nullable()->after('mes_id');

            // Definimos la llave foránea apuntando a la tabla categorias
            $table->foreign('categoria_id')
                  ->references('id')
                  ->on('categorias')
                  ->onDelete('cascade'); 
        });
    }

   
    public function down(): void
    {
        Schema::table('semanas', function (Blueprint $table) {
            // Eliminamos primero la restricción de llave foránea y luego la columna
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });
    }
};
