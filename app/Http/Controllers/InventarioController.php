<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use App\Models\Materiale;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class InventarioController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "referencia_material" => "required|string|exists:materiales,referencia_material",
            "costo" => "required|numeric",
            "cantidad" => "required|numeric",
            "nit_proveedor" => "required",
            "nombre_proveedor" => "required",
        ]);

        if ($validator->fails()) {
            

            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }

        if (!is_string($request->referencia_material)) {
          

            return ResponseHelper::error(422,"El campo referencia_material no es válido");

        }
        $consecutivo = Inventario::where("referencia_material", "=", $request->referencia_material)
            ->max("consecutivo") ?? 0;

        $inventario =  new Inventario();
        $inventario->referencia_material = strtoupper(trim($request->referencia_material));
        // return response()->json($request->referencia_material);
        $inventario->consecutivo = $consecutivo + 1;
        $inventario->costo = (float) trim($request->costo);
        $inventario->cantidad = trim($request->cantidad);
        $inventario->nit_proveedor = trim($request->nit_proveedor);
        $inventario->nombre_proveedor = strtoupper(trim($request->nombre_proveedor));
        $inventario->descripcion_proveedor = "Fer";
        $inventario->numero_identificacion = Auth::user()->numero_identificacion;
        $inventario->save();

        return ResponseHelper::success(200,"Se ha registrado con exito",["inventario" => $inventario]);

    }

    public function update(Request $request)
    {

        try {
            $validatedata = Validator::make($request->all(), [
                "referencia_material"=>"required|exists:inventarios,referencia_material",
                "consecutivo"=>"required|numeric",
                "costo_material" => "required|numeric",
                "cantidad" => "required|numeric|min:1"
            ]);
    
            // Si la validación falla, devolver una respuesta de error 422
            if ($validatedata->fails()) {
                return ResponseHelper::error(422,$validatedata->errors()->first(),$validatedata->errors());
            }
    
            // Buscar el material por referencia
            $inventario = Inventario::where('referencia_material', trim($request->referencia_material))
            ->where("consecutivo",$request->consecutivo)
            ->first();
           
            // Verificar si el material existe
            if (!$inventario) {
               
                return ResponseHelper::error(404,"Inventario no encontrado");
            }
            
           
           
            // Actualizar los datos del material

            $inventario->update([
                $inventario->costo = "1182",
                $inventario->cantidad = 50
            ]);
            // return $inventario;
          
    
            return ResponseHelper::success(200,
             "El inventario se ha actualizado con exito", ['inventario' => $inventario]);
        } catch (Throwable $th) {
           return $th;
        }

        
    }

    public function show($referencia_material){


    }
}
