<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    
   public function index()
{
    return Role::with('permissions')->get();
}

public function getDatosIniciales()
{
    try {
        return response()->json([
            'status' => 'success',
            'roles' => \App\Models\Role::where('estado', 1)->get(),
            'servicios' => \App\Models\Servicio::all(['id', 'nombre']),
            'categorias' => \App\Models\Categoria::all(['id', 'nombre']),            
            'permisos' => \App\Models\Permission::all(['id', 'label', 'subject', 'action'])
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

public function guardarMatrizAccesos(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'role_id' => 'required|exists:roles,id',
        'categorias_ids' => 'array',
        'servicios_ids' => 'array', 
        'permisos_ids' => 'array',  
    ]);

    DB::beginTransaction();
    try {
        $user = \App\Models\User::findOrFail($request->user_id);
        $role = \App\Models\Role::findOrFail($request->role_id);

        $user->roles()->sync([$request->role_id]);
      
        if ($request->has('servicios_ids')) {
            $role->servicios()->sync($request->servicios_ids);
        }
        if ($request->has('categorias_ids')) {
                $role->categorias()->sync($request->categorias_ids);
            }
     
        if ($request->has('permisos_ids')) {
            $role->permissions()->sync($request->permisos_ids);
        }

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Matriz de accesos y configuración de servicios procesada correctamente.'
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Fallo en la transacción: ' . $e->getMessage()
        ], 500);
    }
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|unique:roles,name',
        'descripcion' => 'nullable|string'
    ]);

    return Role::create($request->all());
}

public function show(Role $role)
{
    return $role->load('permissions');
}

public function update(Request $request, Role $role)
{
    $request->validate([
        'name' => 'required|unique:roles,name,' . $role->id,
        'descripcion' => 'nullable|string'
    ]);

    $role->update($request->all());

    return $role;
}

public function destroy(Role $role)
{
    $role->delete();
    return response()->json(['message' => 'Role eliminado']);
}
public function assignPermissions(Request $request, Role $role)
{
    $request->validate([
        'permissions' => 'required|array'
    ]);

    $role->permissions()->sync($request->permissions);

    return response()->json(['message' => 'Permisos asignados correctamente']);
}
}
