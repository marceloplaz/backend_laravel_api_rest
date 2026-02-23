<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class UserController extends Controller
{
    /**
     * Muestra una lista de usuarios con sus turnos y categorías.
     */
    public function index()
    {
        // Cargamos las relaciones 'categoria' y 'turnos' para evitar el problema N+1
        $users = User::with(['categoria', 'turnos', 'persona'])->get();
        return response()->json($users);
    }

    /**
     * Crea un nuevo usuario.
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        // 'confirmed' obliga a enviar un campo llamado 'password_confirmation'
        'password' => 'required|min:8|confirmed', 
        'categoria_id' => 'nullable|exists:categorias,id'
    ]);

   $user = User::create([
    'name' => $validated['name'],
    'email' => $validated['email'],
    'password' => Hash::make($validated['password']),
    'categoria_id' => $validated['categoria_id'] ?? null,
    'must_change_password' => true, // El usuario DEBE cambiarla
]);
    // Opcional: Podrías asignar un rol por defecto aquí si lo necesitas
    // $user->roles()->attach($id_del_rol_medico);

    return response()->json([
        'message' => 'Usuario creado con éxito',
        'user' => $user->load('categoria') // Cargamos la categoría para que el frontend la vea
    ], 201);
}

    /**
     * Muestra un usuario específico con todo su historial de turnos.
     */
    public function show(string $id)
    {
        $user = User::with(['categoria', 'turnos', 'persona', 'roles'])->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

    /**
     * Actualiza los datos del usuario.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'categoria_id' => 'sometimes|exists:categorias,id'
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado',
            'user' => $user
        ]);
    }

public function updatePassword(Request $request, $id)
    {
        // 1. Validar la entrada
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed', // requiere password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 2. Buscar al usuario
        $user = User::findOrFail($id);

        // 3. Lógica de Negocio: Evitar que un Admin (si lograra saltar el middleware)
        // intente cambiar la clave de un Super Admin.
        if ($user->hasRole('super_admin') && !$request->user()->hasRole('super_admin')) {
            return response()->json([
                'message' => 'No tienes jerarquía suficiente para cambiar esta contraseña.'
            ], 403);
        }

        // 4. Actualizar
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Contraseña del usuario ' . $user->name . ' actualizada con éxito.'
        ], 200);
    }
    /**
     * Elimina un usuario. con token
     */
    public function destroy(Request $request, $id)
{
    // 1. Validar que el Admin envió el token de autorización
    $request->validate([
        'auth_token' => 'required|string'
    ]);

    // 2. Buscar si el token existe, es para 'delete_user' y no ha expirado
    $authorization = AdminAuthorization::where('code', $request->auth_token)
        ->where('action', 'delete_user')
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();

    if (!$authorization) {
        return response()->json([
            'message' => 'Token de Super Admin inválido, expirado o ya utilizado.'
        ], 403);
    }

    // 3. Proceder con el borrado (con el escudo de Super Admin que ya tenemos)
    $usuarioAEliminar = User::findOrFail($id);
    
    if ($usuarioAEliminar->hasRole('super_admin')) {
        return response()->json(['message' => 'Imposible eliminar al dueño del sistema'], 403);
    }

    // 4. Marcar el token como usado y borrar
    $authorization->update(['used' => true]);
    $usuarioAEliminar->delete();

    return response()->json(['message' => 'Usuario eliminado con autorización confirmada.']);
}
    
}