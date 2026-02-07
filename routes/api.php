<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; // Sin el "\Api" que tenías antes


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// El prefijo v1 es muy buena práctica para APIs
Route::prefix("v1/auth")->group(function(){
 
    // RUTAS PÚBLICAS
    Route::post("/register", [AuthController::class, "funRegister"]);
    Route::post("/login", [AuthController::class, "funLogin"]); // Con L mayúscula

    // RUTAS PROTEGIDAS (Requieren Token)
    Route::middleware('auth:sanctum')->group(function(){
        Route::post("/logout", [AuthController::class, "funlogout"]); // Cambié /profile por /logout para que tenga sentido
        Route::get("/profile", [AuthController::class, "funprofile"]);
    });
});