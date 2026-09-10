<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ Asegúrate de usar Schema::table, NO Schema::create
        Schema::table('turno_comida', function (Blueprint $table) {
            if (!Schema::hasColumn('turno_comida', 'dia_relativo')) {
                $table->unsignedTinyInteger('dia_relativo')->default(0)->after('comida_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('turno_comida', function (Blueprint $table) {
            if (Schema::hasColumn('turno_comida', 'dia_relativo')) {
                $table->dropColumn('dia_relativo');
            }
        });
    }
};