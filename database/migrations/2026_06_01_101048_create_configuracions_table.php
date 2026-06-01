<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('configuraciones', function (Blueprint $table) {
        $table->id();
        $table->string('clave')->unique(); // Ej: 'periodo_bloqueado'
        $table->string('valor');           // Ej: 'true' o 'false'
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracions');
    }
};
