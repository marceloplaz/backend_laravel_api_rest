<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
{
    return Permission::all();
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|unique:permissions,name',
        'descripcion' => 'nullable|string'
    ]);

    return Permission::create($request->all());
}

public function show(Permission $permission)
{
    return $permission;
}

public function update(Request $request, Permission $permission)
{
    $request->validate([
        'name' => 'required|unique:permissions,name,' . $permission->id,
        'descripcion' => 'nullable|string'
    ]);

    $permission->update($request->all());

    return $permission;
}

public function destroy(Permission $permission)
{
    $permission->delete();
    return response()->json(['message' => 'Permission eliminado']);
}
}
