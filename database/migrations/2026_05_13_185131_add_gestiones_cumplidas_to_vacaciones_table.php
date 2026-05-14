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
    Schema::table('vacaciones', function (Blueprint $table) {
        // Lo colocamos después de los campos de periodo para mantener orden
        $table->string('gestiones_cumplidas')->nullable()->after('periodo_hasta');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            //
        });
    }
};
