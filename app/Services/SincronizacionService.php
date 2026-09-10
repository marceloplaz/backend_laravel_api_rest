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
    /**
     * Sincroniza Auxiliares y Licenciadas en Enfermería
     */
    public function sincronizarDesdeSqlServer()
    {
        $datosSql = DB::connection('sqlsrv_externo')
                    ->table('dbo.VW_PERSONA_SINCRO_INTEGRADA')
                    ->whereIn('especialidad', ['Aux. Enfermeria', 'Lic. Enfermeria']) 
                    ->get();        
                    
        if ($datosSql->isEmpty()) {
            Log::warning("La sincronización de enfermería no encontró datos.");
            return;
        }

        Log::info("Se encontraron " . $datosSql->count() . " registros de enfermería para sincronizar.");
        
        foreach ($datosSql as $item) {
            $this->procesarEnfermeria($item);
        }
    }

    /**
     * Sincroniza Internos y Residentes desde la nueva vista
     */
    public function sincronizarInternosYResidentes()
    {
        $datosSql = DB::connection('sqlsrv_externo')
                    ->table('dbo.vw_personal_internos_residentes')
                    ->get();        
                    
        if ($datosSql->isEmpty()) {
            Log::warning("La sincronización de internos y residentes no encontró datos.");
            return;
        }

        Log::info("Se encontraron " . $datosSql->count() . " registros de internos y residentes para sincronizar.");
        
        foreach ($datosSql as $item) {
            $this->procesarInternosResidentes($item);
        }
    }

    /**
     * Procesamiento exclusivo para Enfermería
     */
    private function procesarEnfermeria($item)
    {
        $tipoTrabajador = str_contains($item->especialidad, 'Lic') ? 'enfermera' : $item->especialidad;
        
        // Si viene vacío o nulo, asignamos '0' para evitar el error de base de datos
        $numeroTipoSalario = !empty($item->numero_tipo_salario) ? $item->numero_tipo_salario : '0';

        Log::info("Item Enfermería detectado:", (array) $item); 
        try {
            DB::transaction(function () use ($item, $tipoTrabajador, $numeroTipoSalario) {
                $ci = trim((string) $item->carnet_identidad); 
                $passwordTemporal = $this->generarPasswordTemporal($ci, $item->nombre_completo);
                $emailUnicoSincro = $ci . '@sincro.local';

                $user = User::updateOrCreate(
                   ['email' => $emailUnicoSincro], 
                    [
                        'name'         => $item->nombre_completo,
                        'password'     => Hash::make($passwordTemporal),
                        'categoria_id' => $item->categoria_id,
                    ]
                );

                Persona::updateOrCreate(
                    ['carnet_identidad' => $ci], 
                    [
                        'user_id'                   => $user->id,
                        'nombre_completo'           => $item->nombre_completo,
                        'fecha_nacimiento'          => $item->fecha_nacimiento,
                        'genero'                    => $item->genero,
                        'telefono'                  => $item->telefono,
                        'direccion'                 => $item->direccion,
                        'tipo_trabajador'           => $tipoTrabajador, 
                        'nacionalidad'              => 'Boliviana',
                        'tipo_salario'              => 'TGN', 
                        'numero_tipo_salario'       => $numeroTipoSalario,
                        'fecha_ingreso_institucion' => $item->fecha_ingreso_institucion,
                    ]
                );
            });
        } catch (\Exception $e) {
            echo "ERROR DETECTADO: " . $e->getMessage() . "\n";
            Log::error("Error real: " . $e->getMessage());
            throw $e; 
        }
    }

    /**
     * Procesamiento exclusivo para Internos y Residentes
     */
    private function procesarInternosResidentes($item)
    {
        $esInterno = str_contains($item->especialidad, 'Interno');
        
        $tipoTrabajador = $esInterno ? 'Internos' : 'Residentes';
        $tipoSalario    = $esInterno ? 'INTERNOS' : 'RESIDENTES';
        
        // Si viene vacío o nulo, asignamos '0'
        $numeroTipoSalario = !empty($item->numero_tipo_salario) ? $item->numero_tipo_salario : '0';

        Log::info("Item Interno/Residente detectado:", (array) $item); 
        try {
            DB::transaction(function () use ($item, $tipoTrabajador, $tipoSalario, $numeroTipoSalario) {
                $ci = trim((string) $item->carnet_identidad); 
                $passwordTemporal = $this->generarPasswordTemporal($ci, $item->nombre_completo);
                $emailUnicoSincro = $ci . '@sincro.local';

                $user = User::updateOrCreate(
                   ['email' => $emailUnicoSincro], 
                    [
                        'name'         => $item->nombre_completo,
                        'password'     => Hash::make($passwordTemporal),
                        'categoria_id' => $item->categoria_id,
                    ]
                );

                Persona::updateOrCreate(
                    ['carnet_identidad' => $ci], 
                    [
                        'user_id'                   => $user->id,
                        'nombre_completo'           => $item->nombre_completo,
                        'fecha_nacimiento'          => $item->fecha_nacimiento,
                        'genero'                    => $item->genero,
                        'telefono'                  => $item->telefono,
                        'direccion'                 => $item->direccion,
                        'tipo_trabajador'           => $tipoTrabajador, 
                        'nacionalidad'              => 'Boliviana',
                        'tipo_salario'              => $tipoSalario,    
                        'numero_tipo_salario'       => $numeroTipoSalario, 
                        'fecha_ingreso_institucion' => $item->fecha_ingreso_institucion,
                    ]
                );
            });
        } catch (\Exception $e) {
            echo "ERROR DETECTADO: " . $e->getMessage() . "\n";
            Log::error("Error real: " . $e->getMessage());
            throw $e; 
        }
    }

    /**
     * Función auxiliar privada para calcular la contraseña temporal
     */
    private function generarPasswordTemporal($ci, $nombreCompleto)
    {
        $ciDigitos = str_split($ci);
        $multiplicacion = 1;
        foreach ($ciDigitos as $digito) {
            $d = (int)$digito;
            if ($d > 0) { $multiplicacion *= $d; }
        }
        
        $partes = preg_split('/\s+/', trim($nombreCompleto)); 
        $nombre = isset($partes[0]) ? strtolower($partes[0]) : 'x';
        $apellido = isset($partes[1]) ? strtolower(substr($partes[1], 0, 2)) : 'xx';
        
        return $multiplicacion . $apellido . $nombre;
    }
}