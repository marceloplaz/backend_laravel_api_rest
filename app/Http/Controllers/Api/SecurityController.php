<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // Importante para el código aleatorio
use App\Models\AdminAuthorization; // Tu modelo de la tabla nueva
use Illuminate\Support\Facades\Auth;

class SecurityController extends Controller
{
    public function generateToken(Request $request) 
    {
        // Generamos un código único de 8 caracteres
        $code = Str::upper(Str::random(8)); 
        
        AdminAuthorization::create([
            'code' => $code,
            'super_admin_id' => Auth::id(),
            'action' => 'delete_user',
            'expires_at' => now()->addMinutes(15), // El admin tiene 15 min para usarlo
            'used' => false
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Token de autorización generado',
            'token_autorizacion' => $code,
            'instrucciones' => 'Entregue este código al administrador. Expira en 15 minutos.'
        ]);
    }
}