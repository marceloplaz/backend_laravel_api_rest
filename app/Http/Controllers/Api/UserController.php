<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // Para transacciones
class UserController extends Controller
{
    /**
     * Muestra una lista de usuarios con sus turnos y categorías.
     */
public function index(Request $request)
{
    $buscar = $request->query('buscar');

    // Buscamos usuarios que no estén ya vinculados o simplemente todos para filtrar
    $usuarios = \App\Models\User::with(['persona', 'categoria'])
        ->when($buscar, function ($query) use ($buscar) {
            $query->where(function ($q) use ($buscar) {
                // Busca por el nombre de usuario en la tabla users
                $q->where('name', 'like', "%{$buscar}%")
                  // O busca por el nombre completo en la tabla personas
                  ->orWhereHas('persona', function ($subQuery) use ($buscar) {
                      $subQuery->where('nombre_completo', 'like', "%{$buscar}%");
                  });
            });
        })
        ->limit(10)
        ->get();

    return response()->json($usuarios, 200);
}

public function store(Request $request)
    {
        // 1. Validación estricta
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|min:8|confirmed',
            'categoria_id'          => 'nullable|exists:categorias,id',
            'roles'                 => 'required|array',
            'roles.*'               => 'exists:roles,id',
            // Validación de los datos anidados de la persona
            'persona'               => 'required|array',
            'persona.nombre_completo' => 'required|string|max:255',
            'persona.carnet_identidad' => 'required|string|unique:personas,carnet_identidad',
            'persona.tipo_trabajador' => 'required|string',
        ]);

        try {
            // Usamos una transacción para que si algo falla, no quede un usuario sin persona o viceversa
            return DB::transaction(function () use ($request) {
                
                // 2. Crear el Usuario
                $user = User::create([
                    'name'         => $request->name,
                    'email'        => $request->email,
                    'password'     => Hash::make($request->password),
                    'categoria_id' => $request->categoria_id,
                ]);

                // 3. Crear la Persona vinculada
                // Laravel automáticamente inyectará el 'user_id' gracias a la relación hasOne
                $user->persona()->create($request->persona);

                // 4. Asignar Roles (Tabla Pivot role_user)
                if ($request->has('roles')) {
                    $user->roles()->sync($request->roles);
                }

                // 5. Cargar relaciones para la respuesta
                $user->load(['persona', 'roles', 'categoria']);

                return response()->json([
                    'message' => 'Usuario, Persona y Roles registrados exitosamente.',
                    'user'    => $user
                ], 201);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error en el proceso de registro maestro',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    /**
     * Crea un nuevo usuario.
     */

 
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

    // 1. Validación dinámica de la contraseña
    $rules = [
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,' . $id,
        'categoria_id' => 'sometimes|exists:categorias,id',
        'roles' => 'sometimes|array',
        'persona' => 'sometimes|array',
        'persona.carnet_identidad' => 'sometimes|unique:personas,carnet_identidad,' . ($user->persona->id ?? 0),
    ];

    // Si el usuario envió una contraseña, validamos que tenga mínimo 8 caracteres
    if ($request->filled('password')) {
        $rules['password'] = 'required|min:8|confirmed'; // Requiere password_confirmation
    }

    $validated = $request->validate($rules);

    // 2. Actualizar datos básicos
    $user->fill($request->only(['name', 'email', 'categoria_id']));

    // 3. Lógica para la Contraseña: Solo si se proporcionó una nueva
    if ($request->filled('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    // 4. Actualizar relación Persona (CI, Teléfono, etc.)
    if ($request->has('persona')) {
        $user->persona()->update($request->input('persona'));
    }

    // 5. Sincronizar roles
    if ($request->has('roles')) {
        $user->roles()->sync($request->roles);
    }

    return response()->json([
        'message' => 'Usuario y contraseña actualizados correctamente',
        'user' => $user->load(['persona', 'roles'])
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
/**
 * Obtiene la lista de roles activos para los selectores del frontend.
 */
public function getRoles()
{
    // Importa el modelo: use App\Models\Role; al inicio del archivo
    // Consultamos la tabla 'roles' que vimos en tu Laragon
    $roles = \DB::table('roles')->where('estado', 1)->get(); 
    
    return response()->json([
        'success' => true,
        'data'    => $roles
    ]);
}
    
}