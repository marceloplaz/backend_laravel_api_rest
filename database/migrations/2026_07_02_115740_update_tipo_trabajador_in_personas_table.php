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
    // Cambiamos el ENUM agregando el nuevo valor
    DB::statement("ALTER TABLE personas MODIFY COLUMN tipo_trabajador ENUM('medico', 'enfermera', 'manual', 'chofer', 'administrativo', 'tecnico', 'bioquimico', 'Imagenologo', 'Aux. Enfermeria')");
}

public function down(): void
{
    // En caso de que necesites revertir, volvemos a la lista anterior
    DB::statement("ALTER TABLE personas MODIFY COLUMN tipo_trabajador ENUM('medico', 'enfermera', 'manual', 'chofer', 'administrativo')");
}
};
