<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Usamos una sentencia SQL directa porque modificar ENUMs con Blueprint a veces da problemas
        DB::statement("ALTER TABLE personas MODIFY COLUMN tipo_trabajador ENUM(
            'medico', 
            'enfermera', 
            'manual', 
            'chofer', 
            'administrativo', 
            'tecnico', 
            'bioquimico'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Volvemos al estado original si es necesario
        DB::statement("ALTER TABLE personas MODIFY COLUMN tipo_trabajador ENUM(
            'medico', 
            'enfermera', 
            'manual', 
            'chofer', 
            'administrativo'
        ) NOT NULL");
    }
};