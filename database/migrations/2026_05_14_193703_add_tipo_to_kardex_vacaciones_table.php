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
    Schema::table('kardex_vacaciones', function (Blueprint $table) {
        // Agregamos la columna tipo después de user_id
        $table->string('tipo', 20)->after('user_id')->nullable();
    });
}

public function down(): void
{
    Schema::table('kardex_vacaciones', function (Blueprint $table) {
        $table->dropColumn('tipo');
    });
}
};
