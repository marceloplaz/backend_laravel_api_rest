<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
{
    Schema::table('areas', function (Blueprint $table) {

        $table->foreignId('categoria_id')
              ->nullable() // Cambia a nullable si las áreas pueden existir sin categoría
              ->constrained('categorias') 
              ->onDelete('set null'); // Si se borra la categoría, el área se queda con null
    });
}

public function down()
{
    Schema::table('areas', function (Blueprint $table) {
        $table->dropForeign(['categoria_id']);
        $table->dropColumn('categoria_id');
    });
}
};
