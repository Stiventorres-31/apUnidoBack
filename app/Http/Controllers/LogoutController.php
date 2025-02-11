<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        try {
            auth('api')->logout();
            JWTAuth::invalidate(JWTAuth::getToken());
           
            return ResponseHelper::success(200,"Se ha cerrado con exito",[]);
        } catch (JWTException $ex) {
          
            return ResponseHelper::error(401,"El token no existe",[]);
        } catch (Throwable $th){
            Log::error("error al realizar el logout " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
