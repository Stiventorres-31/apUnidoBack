<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $credenciales = $request->only("numero_identificacion", "password");

        try {
            if (!$token = JWTAuth::attempt($credenciales)) {
                return ResponseHelper::error(401, "Las credenciales no son correctas", []);
            }
        } catch (JWTException  $e) {

            return ResponseHelper::error(422, "No se ha podido iniciar sesion", []);
        }

        return ResponseHelper::success(200, "Se ha iniciado sesión con exito", [
            'token' => $token,
            'user' => auth::user()
        ]);
    }
}
