<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\TipoInmueble;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TipoInmuebleController extends Controller
{
    public function index()
    {
        $tiposInmuebles = TipoInmueble::all();
        return ResponseHelper::success(200,"Todos los tipos de inmuebles registrados",["tipo_inmueble" => $tiposInmuebles]);
    }

    public function indexActivated(){
        $tiposInmuebles = TipoInmueble::where("estado","=","A")->get();
        return ResponseHelper::success(200,"Todos los tipos de inmuebles activos",["tipo_inmueble" => $tiposInmuebles]);
    }

    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'nombre_tipo_inmueble' => 'required|string|unique:tipo_inmuebles,nombre_tipo_inmueble|max:255',

        ]);

        if ($validator->fails()) {
         

            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }

        $tipoInmueble = new TipoInmueble();
        $tipoInmueble->nombre_tipo_inmueble = strtoupper($request->nombre_tipo_inmueble);
        $tipoInmueble->numero_identificacion = Auth::user()->numero_identificacion;
        $tipoInmueble->estado = "A";
        $tipoInmueble->save();

        return ResponseHelper::success(201,"Tipo de inmueble creado con éxito",["tipo_inmueble" => $tipoInmueble]);
    }

    public function show($id)
    {
        

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:tipo_inmuebles,id', // Verifica que exista el ID en la tabla tipo_inmuebles
        ]);
    
        if ($validator->fails()) {
            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }

        $tipoInmueble = TipoInmueble::find($id);
        return ResponseHelper::success(200,"Tipo de inmueble encontrado",["tipo_inmueble" => $tipoInmueble]);
    }
    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            "id"=>"required|exists:tipo_inmuebles,id",
            'nombre_tipo_inmueble' => 'required|string|unique:tipo_inmuebles,nombre_tipo_inmueble,' . $id,

        ]);
        
        if ($validator->fails()) {
            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }
    
        $tipoInmueble = TipoInmueble::find($id);

        $tipoInmueble->update([
            'nombre_tipo_inmueble' => $request->nombre_tipo_inmueble,
            // 'numero_identificacion' => auth::user()->numero_identificacion

        ]);

      
        return ResponseHelper::success(201,"Tipo de inmueble actualizado exitosamente",["tipo_inmueble" => $tipoInmueble]);

    }

    public function destroy($id)
    {

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:tipo_inmuebles,id', // Verifica que exista el ID en la tabla tipo_inmuebles
        ]);
    
        if ($validator->fails()) {
            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }


        $tipo_inmueble = TipoInmueble::find($id);

        $tipo_inmueble->estado = ($tipo_inmueble->estado === "E") ? "A": "E";
        $tipo_inmueble->save();
      

        return ResponseHelper::success(200,"Se ha eliminado con exito");

    }
}
