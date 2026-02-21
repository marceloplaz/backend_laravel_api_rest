<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            'password' => 'required|min:8',
            'categoria_id' => 'nullable|exists:categorias,id'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'categoria_id' => $validated['categoria_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Usuario creado con éxito',
            'user' => $user
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

    /**
     * Elimina un usuario.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}