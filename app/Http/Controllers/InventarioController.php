<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
}
