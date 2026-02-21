<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index()
{
    return Role::with('permissions')->get();
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
