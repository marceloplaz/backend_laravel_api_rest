<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
{
    Schema::table('incidencias_tecnicas', function (Blueprint $table) {
        // Al ser un string y no tener relación formal, solo borramos la columna
        $table->dropColumn('categoria');
    });
}

public function down(): void
{
    Schema::table('incidencias_tecnicas', function (Blueprint $table) {
        $table->string('categoria')->nullable();
    });
}
};
