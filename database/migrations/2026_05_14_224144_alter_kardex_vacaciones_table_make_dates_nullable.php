<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kardex_vacaciones', function (Blueprint $blueprint) {
            // Hacemos que las fechas y otros campos sean nullables
            $blueprint->date('fecha_inicio')->nullable()->change();
            $blueprint->date('fecha_fin')->nullable()->change();
            
            // Si también te dio problemas 'motivo_tipo' y quieres agregarlo de una vez:
            if (!Schema::hasColumn('kardex_vacaciones', 'motivo_tipo')) {
                $blueprint->string('motivo_tipo')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kardex_vacaciones', function (Blueprint $blueprint) {
            $blueprint->date('fecha_inicio')->nullable(false)->change();
            $blueprint->date('fecha_fin')->nullable(false)->change();
            
            if (Schema::hasColumn('kardex_vacaciones', 'motivo_tipo')) {
                $blueprint->dropColumn('motivo_tipo');
            }
        });
    }
};