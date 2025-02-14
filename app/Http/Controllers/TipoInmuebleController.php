<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\TipoInmueble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TipoInmuebleController extends Controller
{
    public function index()
    {
        try {
            $tiposInmuebles = TipoInmueble::where("estado", "A")->with("usuario")->get();
            return ResponseHelper::success(200, "Todos los tipos de inmuebles registrados", ["tipo_inmueble" => $tiposInmuebles]);
        } catch (Throwable $th) {
            Log::error("error al obtener todos los tipos de inmuebles " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    // public function indexActivated(){
    //     $tiposInmuebles = TipoInmueble::where("estado","=","A")->get();
    //     return ResponseHelper::success(200,"Todos los tipos de inmuebles activos",["tipo_inmueble" => $tiposInmuebles]);
    // }

    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'nombre_tipo_inmueble' => 'required|string|unique:tipo_inmuebles,nombre_tipo_inmueble|max:255'
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $tipoInmueble = new TipoInmueble();
            $tipoInmueble->nombre_tipo_inmueble = strtoupper($request->nombre_tipo_inmueble);
            $tipoInmueble->numero_identificacion = Auth::user()->numero_identificacion;
            $tipoInmueble->estado = "A";
            $tipoInmueble->save();

            return ResponseHelper::success(201, "Tipo de inmueble creado con éxito", ["tipo_inmueble" => $tipoInmueble]);
        } catch (Throwable $th) {
            Log::error("error al registrar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function show($id)
    {


        try {
            $validator = Validator::make(['id' => $id], [
                'id' => 'required|exists:tipo_inmuebles,id', // Verifica que exista el ID en la tabla tipo_inmuebles
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
            }

            $tipoInmueble = TipoInmueble::with("usuario")->find($id);
            return ResponseHelper::success(200, "Tipo de inmueble encontrado", ["tipo_inmueble" => $tipoInmueble]);
        } catch (Throwable $th) {
            Log::error("error al consultar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            "id" => "required|exists:tipo_inmuebles,id",
            'nombre_tipo_inmueble' => 'required|string|unique:tipo_inmuebles,nombre_tipo_inmueble,' . $id,

        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $tipoInmueble = TipoInmueble::find($id);

            $tipoInmueble->update([
                'nombre_tipo_inmueble' => strtoupper(trim($request->nombre_tipo_inmueble))
            ]);


            return ResponseHelper::success(201, "Tipo de inmueble actualizado exitosamente", ["tipo_inmueble" => $tipoInmueble]);
        } catch (Throwable $th) {
            Log::error("error al actualizar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function destroy(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:tipo_inmuebles,id', // Verifica que exista el ID en la tabla tipo_inmuebles
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        try {
            $tipo_inmueble = TipoInmueble::find($request->id);

            // $tipo_inmueble->estado = ($tipo_inmueble->estado === "E") ? "A": "E";
            $tipo_inmueble->estado = "E";
            $tipo_inmueble->save();

            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (Throwable $th) {

            Log::error("error al eliminar un tipo de inmueble " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
