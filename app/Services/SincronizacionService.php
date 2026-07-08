<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Persona;
use Exception;

class SincronizacionService
{
    public function sincronizarDesdeSqlServer()
    {
        $datosSql = DB::connection('sqlsrv_externo')
                        ->table('dbo.VW_PERSONA_SINCRO_INTEGRADA')
                        ->whereIn('especialidad', ['Aux. Enfermeria']) 
                        ->get();    
                        if ($datosSql->isEmpty()) {
        Log::warning("La sincronización no encontró datos. Revisa si el filtro 'especialidad' es correcto o si la tabla está vacía.");
        return;
    }

    Log::info("Se encontraron " . $datosSql->count() . " registros para sincronizar.");
        
        foreach ($datosSql as $item) {
          Log::info("Item detectado:", (array) $item); 
        try {
                DB::transaction(function () use ($item) {
                    
                    // --- 1. Lógica de Contraseña ---
                    $ci = trim((string) $item->carnet_identidad); // Limpieza de espacios en blanco
                    $ciDigitos = str_split($ci);
                    
                    $multiplicacion = 1;
                    foreach ($ciDigitos as $digito) {
                        $d = (int)$digito;
                        if ($d > 0) { $multiplicacion *= $d; }
                    }
                    
                    $nombreCompleto = trim($item->nombre_completo);
                    $partes = preg_split('/\s+/', $nombreCompleto); 
                    $nombre = isset($partes[0]) ? strtolower($partes[0]) : 'x';
                    $apellido = isset($partes[1]) ? strtolower(substr($partes[1], 0, 2)) : 'xx';
                    
                    $passwordTemporal = $multiplicacion . $apellido . $nombre;
                    // -------------------------------

                    // 2. Definir un email único
                    $emailUnicoSincro = trim($item->carnet_identidad) . '@sincro.local';

                    $user = User::updateOrCreate(
                     ['email' => $emailUnicoSincro], 
                        [
                            'name'         => $item->nombre_completo,
                            'password'     => Hash::make($passwordTemporal),
                            'categoria_id' => $item->categoria_id,
                        ]
                    );

                    // 4. Crear o actualizar la Persona
                    Persona::updateOrCreate(
                        ['carnet_identidad' => $ci], // Búsqueda estricta por CI limpio
                        [
                            'user_id'                   => $user->id, // Asignación directa del ID limpio
                            'nombre_completo'           => $item->nombre_completo,
                            'fecha_nacimiento'          => $item->fecha_nacimiento,
                            'genero'                    => $item->genero,
                            'telefono'                  => $item->telefono,
                            'direccion'                 => $item->direccion,
                            'tipo_trabajador'           => 'Aux. Enfermeria', // <-- CORREGIDO: Dejó de ser administrativo
                            'nacionalidad'              => 'Boliviana',
                            'tipo_salario'              => 'TGN',
                            'numero_tipo_salario'       => $item->numero_tipo_salario,
                            'fecha_ingreso_institucion' => $item->fecha_ingreso_institucion,
                        ]
                    );
                });
            } catch (\Exception $e) {
               echo "ERROR DETECTADO: " . $e->getMessage() . "\n";
    \Log::error("Error real: " . $e->getMessage());
    throw $e; // ESTO DETENDRÁ EL SCRIPT SI HAY ERROR
            }
        }
    }
}

