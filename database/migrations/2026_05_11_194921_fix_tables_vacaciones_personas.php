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
    Schema::table('personas', function (Blueprint $table) {
        $table->date('fecha_ingreso_institucion')->nullable()->after('numero_tipo_salario');
    });

    Schema::table('vacaciones', function (Blueprint $table) {
        $table->foreignId('categoria_id')->nullable()->constrained('categorias')->after('servicio_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
