<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function funRegister(Request $request)
    { 
        $request->validate([
            "name"      => "required|string|min:3|max:30",
            "email"     => "required|email|unique:users,email",
            "password"  => "required|min:8",    
            "password2" => "required|same:password"
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            "message" => "Usuario registrado con éxito",
            "status"  => 201
        ], 201);
    } 

    public function funLogin(Request $request)
    {
        $credentials = $request->validate([
            "email" => "required|email",
            "password" => "required"
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(["message" => "Credenciales incorrectas"], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            "access_token" => $token,
            "token_type" => "Bearer",
            "user" => $user
        ]);
    }

    public function funprofile(Request $request)
    {
        return response()->json($request->user());
    }

    public function funlogout(Request $request)
    {
        // Añadimos una verificación por si el token ya no existe
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(["message" => "Sesión cerrada correctamente"]);
        }
        
        return response()->json(["message" => "No hay sesión activa"], 401);
    }
}