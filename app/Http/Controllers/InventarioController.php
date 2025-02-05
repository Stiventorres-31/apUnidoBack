<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventarioController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "referencia_material" => "required|exists:materiales,referencia_material",
            "numero_identificacion" => "required|exists:usuarios,numero_identificacion|max:20|min:6",
            "costo" => "required|regex:/^\d{1,10}(\.\d{1,2})?$/",
            "cantidad" => "required|numeric|min:0",
            "nit_proveedor" => "required|min:6",
            "nombre_proveedor" => "required|min:6",
            "descripcion_proveedor" => "required|min:6",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }

        // return response()->json(Inventario::max("referencia_material") );
        $inventario = new Inventario();
            
        $inventario->referencia_material = $request->referencia_material;
        $inventario->numero_identificacion = $request->numero_identificacion;
        $inventario->costo = $request->costo;
        $inventario->cantidad = $request->cantidad;
        $inventario->nit_proveedor = $request->nit_proveedor;
        $inventario->nombre_proveedor = $request->nombre_proveedor;
        $inventario->descripcion_proveedor = $request->descripcion_proveedor;
        $inventario->consecutivo =  Inventario::max("referencia_material") + 1;
        $inventario->estado = "A";
        $inventario->save();

        return ResponseHelper::success(201,"Se ha registrado con exito",["inventario" => $inventario]);

    }
}
