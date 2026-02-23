<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
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
            "user"    => new UserResource($user)
        ], 201);
       
    } 

    public function funLogin(Request $request)
{
    $credentials = $request->validate([
        "email" => "required|email",
        "password" => "required"
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(["message" => "Usuario o contraseña no coinciden"], 401);
    }

    $user = Auth::user(); 
    $token = $user->createToken('auth_token')->plainTextToken;

    // Preparamos el mensaje según el estado del usuario
    $mensaje = "¡Bienvenido, " . $user->name . "!";
    if ($user->must_change_password) {
        $mensaje = "Hola " . $user->name . ". Por seguridad, recuerde cambiar su contraseña inicial por una nueva.";
    }

    return response()->json([
        "access_token" => $token,
        "token_type"   => "Bearer",
        "user"         => new UserResource($user),
        "must_change_password" => (bool)$user->must_change_password,
        "message"      => $mensaje // Este mensaje lo muestras en un cartelito (toast)
    ], 200);
}

 public function funprofile(Request $request)
{
    $user = $request->user();
    
    // Si el frontend pide el perfil, incluimos una nota de aviso
    $aviso = $user->must_change_password 
             ? "Recuerde: Aún tiene la contraseña asignada por el hospital. Cámbiela pronto por su seguridad." 
             : "Su cuenta está protegida correctamente.";

    return [
        "data" => new UserResource($user),
        "aviso_seguridad" => $aviso
    ];
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