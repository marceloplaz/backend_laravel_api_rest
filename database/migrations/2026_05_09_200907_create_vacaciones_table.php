<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();
            
            // 1. RELACIONES
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('servicio_id')->constrained('servicios');
            $table->foreignId('gestion_id')->constrained('gestiones');

            // 2. ANTIGÜEDAD Y PERIODO (TIEMPO DE AÑO A AÑO QUE CORRESPONDE LA VACACION)
            $table->date('fecha_ingreso_institucion'); 
            $table->date('periodo_desde'); 
            $table->date('periodo_hasta'); 

            // 3. CONTROL DE SALDOS DINÁMICOS
            $table->integer('total_dias_derecho'); 
            $table->integer('dias_consumidos');    
            $table->integer('saldo_restante');     

            // 4. DATOS DE LA AUSENCIA
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('dias_solicitados');

            // 5. NATURALEZA Y MOTIVOS
            $table->boolean('permiso_cuenta')->default(false); 
            $table->enum('motivo_tipo', [
                'VACACION_PROGRAMADA', 
                'SALUD',               
                'TRAMITE',             
                'FAMILIAR',            
                'PARTICULAR',          
                'OTRO'
            ])->default('VACACION_PROGRAMADA');
            $table->string('motivo_detalle')->nullable(); 

            // 6. ESTADOS DE LA SOLICITUD
            // 0: Sin asignar, 1: Asignado, 2: Rechazado
            $table->tinyInteger('estado')->default(0)->comment('0:Sin asignar, 1:Asignado, 2:Rechazado');
            
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->text('observaciones')->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};