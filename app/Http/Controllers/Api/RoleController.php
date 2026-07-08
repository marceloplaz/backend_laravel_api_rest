<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
            // Usamos los campos reales de tu tabla 'permissions'
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
        'servicios_ids' => 'array', // IDs para la tabla role_servicio
        'permisos_ids' => 'array',  // IDs para la tabla permission_role
    ]);

    DB::beginTransaction();
    try {
        $user = \App\Models\User::findOrFail($request->user_id);
        $role = \App\Models\Role::findOrFail($request->role_id);

        // 1. Asignar el Rol al Usuario (Tabla: role_user)
        // sync() elimina registros previos del usuario en esta tabla intermediaria y deja solo el nuevo
        $user->roles()->sync([$request->role_id]);

        // 2. Sincronizar los Servicios vinculados a este Rol (Tabla: role_servicio)
        if ($request->has('servicios_ids')) {
            $role->servicios()->sync($request->servicios_ids);
        }

        // 3. Sincronizar los Permisos directos del Rol (Tabla: permission_role)
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
