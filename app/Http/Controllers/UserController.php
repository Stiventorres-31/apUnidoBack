<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UserController extends Controller
{
    public function index()
    {
        $user = User::where("estado", "=", "A")->get();

        return ResponseHelper::success(200, "Todos los usuario registrados", ["usuarios" => $user]);
    }

    public function show($numero_identificacion)
    {
        try {
            $usuario = User::findOrFail($numero_identificacion);


            return ResponseHelper::success(200, "Usuario encontrado", ["usuario" => $usuario]);
        } catch (ModelNotFoundException $e) {
            // return response()->json([
            //     'isError' => true,
            //     'code' => 404,
            //     'message' => __('El usuario no existe'),
            //     'result' => [],
            // ], 404);
            return ResponseHelper::error(404, "El usuario no existe", []);
        }
    }


    public function store(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            'numero_identificacion' => 'required|unique:usuarios|max:20',
            'nombre_completo' => 'required|max:50',
            'password' => 'required|min:6',
            'rol_usuario' => 'required|array',
            "rol_usuario.id" => "required|min:1",
            "rol_usuario.name" => "required|max:20"
        ]);

        if ($validateData->fails()) {

            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }
        try {
            $usuario = new User();
            $usuario->numero_identificacion = $request->numero_identificacion;
            $usuario->nombre_completo = strtoupper($request->nombre_completo);
            $usuario->password = ($request->password);
            // $usuario->password = hash::make($request->password);
            $usuario->rol_usuario = strtoupper($request->rol_usuario["name"]);
            $usuario->estado = 'A';

            $usuario->save();
         

            return ResponseHelper::success(201, "Usuario creado exitosamente.", ['usuario' => $usuario]);
        } catch (Throwable $th) {
            Log::error("error al crear un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function update(Request $request, $numero_identificacion)
    {
        $validateData = Validator::make($request->all(), [
            'nombre_completo' => 'required|max:50',
            'rol_usuario' => 'required|array',
            "rol_usuario.id" => "required|min:1",
            "rol_usuario.name" => "required|max:20",

        ]);

        if ($validateData->fails()) {
            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }

        try {
            $usuario = User::find($numero_identificacion);
            if (!$usuario) {
    
                return ResponseHelper::error(404, "El usuario no existe", []);
            }
    
            $usuario->nombre_completo = strtoupper($request->input("nombre_completo"));
            $usuario->rol_usuario = strtoupper($request->input("rol_usuario")["name"]);
            $usuario->save();
    
            return ResponseHelper::success(200, "Usuario ha actualizado exitosamente.", ["usuario" => $usuario]);
        } catch (Throwable $th){
            Log::error("error al actualizar el usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
        
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "numero_identificacion" => "required|exists:usuarios,numero_identificacion|min:6"
        ]);

        if ($validator->fails()) {


            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $usuario = User::find($request->numero_identificacion);
            if (!$usuario) {
                return ResponseHelper::error(404, "El usuario no existe", []);
            }
            $usuario->update([
                "estado" => "E"
            ]);
    
            return ResponseHelper::success(200, "Se ha eliminado el usuario", []);
        } catch (Throwable $th){
            Log::error("error al eliminar el usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }

        
    }

    public function changePassword(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            "password" => "required|min:6",
            "new_password" => "required|min:6|confirmed",
        ]);

        // Si la validación falla, retornar error
        if ($validateData->fails()) {
            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }

        try {
            // Obtener el usuario autenticado
        $usuario = Auth::user();

        // Verificar si la contraseña actual es correcta
        if (!Hash::check($request->password, $usuario->password)) {
            return ResponseHelper::error(400, "La contraseña actual no es correcta", []);
        }
        if (!$usuario instanceof User) {
            return ResponseHelper::error(500, "No se pudo obtener el usuario autenticado", []);
        }
        // Actualizar la contraseña
        $usuario->password = Hash::make($request->input('new_password'));
        $usuario->save(); // ⚠️ IMPORTANTE: Guardar los cambios

        return ResponseHelper::success(200, "Contraseña actualizada con éxito", []);
        } catch (Throwable $th){
            Log::error("error al cambiar la contraseña del usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }

        
    }

    public function changePasswordAdmin(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            "numero_identificacion" => "required|exists:usuarios,numero_identificacion",
            "new_password" => "required|min:6|confirmed",
        ]);

        if ($validateData->fails()) {
            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }

        try {
            $usuario = User::where("numero_identificacion", $request->numero_identificacion)->first();
            $usuario->password = Hash::make($request->new_password);
            $usuario->save();
    
    
            return ResponseHelper::success(200, "Contraseña actualizada con éxito", []);
        } catch (Throwable $th){
            Log::error("error cuando el administrador intenta cambiar la contraseña a un usuario " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
        
    }
}
