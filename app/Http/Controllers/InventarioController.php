<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inventario;
use App\Models\Materiale;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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


            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            // if (!is_string($request->referencia_material)) {


            //     return ResponseHelper::error(422, "El campo referencia_material no es válido");
            // }
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

            return ResponseHelper::success(200, "Se ha registrado con exito", ["inventario" => $inventario]);
        } catch (Throwable $th) {
            Log::error("Error al registrar un lote " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function update(Request $request)
    {

        try {
            $validatedata = Validator::make($request->all(), [
                "referencia_material" => "required|exists:inventarios,referencia_material",
                "consecutivo" => "required|numeric",
                "costo_material" => "required|numeric",
                "cantidad" => "required|numeric|min:1"
            ]);

            // Si la validación falla, devolver una respuesta de error 422
            if ($validatedata->fails()) {
                return ResponseHelper::error(422, $validatedata->errors()->first(), $validatedata->errors());
            }

            // Buscar el material por referencia
            $inventario = Inventario::where('referencia_material', trim($request->referencia_material))
                ->where("consecutivo", $request->consecutivo)
                ->first();


            if (!$inventario) {

                return ResponseHelper::error(404, "Inventario no encontrado");
            }



            Inventario::where('referencia_material', $request->referencia_material)
                ->where('consecutivo', $request->consecutivo)
                ->update([
                    'costo' => $request->costo_material,
                    'cantidad' => $request->cantidad
                ]);


            return ResponseHelper::success(
                200,
                "El inventario se ha actualizado con exito",
                ['inventario' => $inventario]
            );
        } catch (Throwable $th) {
            return ResponseHelper::error(500, "Error interno del servidor", ["error" => $th->getMessage()]);
        }
    }

    public function show($referencia_material, $consecutivo)
    {
        $validator = Validator::make(["referencia_material" => $referencia_material, "consecutivo" => $consecutivo], [
            "referencia_material" => "required|string",
            "consecutivo" => "required|numeric"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $inventarios = Inventario::where("referencia_material", $referencia_material)
                ->where("consecutivo", $consecutivo)
                ->get();

            if (!$inventarios) {
                return ResponseHelper::error(404, "No se ha encontrado inventario de ese material");
            }

            return ResponseHelper::error(200, "Se ha encontrado inventario de este material", ["inventarios" => $inventarios]);
        } catch (Throwable $th) {
            Log::error("Error al consultar un inventario por referencia del material y consecutivo " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        } catch (ModelNotFoundException $e){
            
            return ResponseHelper::error(422, "Error interno en el servidor");
        }
    }
}
