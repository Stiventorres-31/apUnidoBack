<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inventario;
use App\Models\Materiale;
use App\Models\Presupuesto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AsignacioneController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            "inmueble_id" => 'required|integer|exists:inmuebles,id',
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto",
            "materiales" => "required|array",
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
        }
        $inmuebleExisteEnPresupuesto = Presupuesto::where("inmueble_id", $request->inmueble_id)->exists();
        if (!$inmuebleExisteEnPresupuesto) {
            return ResponseHelper::error(422, "El inmueble no tiene un presupuesto asignado");
        }


        try {
            DB::beginTransaction();

            foreach ($request->materiales as $material) {
                $validatedData = Validator::make($material, [
                    "referencia_material"  => "required|string|max:10|exists:materiales,referencia_material|exists:presupuestos,referencia_material",
                    "consecutivo" => "required",
                    "cantidad_material"    => "required|numeric",
                    "costo_material"       => "required|numeric",
                ]);



                if ($validatedData->fails()) {
                    DB::rollBack();
                    return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
                }

                $existenciaAsingacion = Asignacione::where("referencia_material", strtoupper(trim($material["referencia_material"])))
                    ->where("codigo_proyecto", strtoupper(trim($request->codigo_proyecto)))
                    ->where("consecutivo", $material["consecutivo"])
                    ->exists();


                if ($existenciaAsingacion) {
                    DB::rollBack();
                    return ResponseHelper::error(500, "Ya existe asignación del material '{$material["referencia_material"]}' con lote '{$material["consecutivo"]}'");
                }

                //VALIDAR EXISTENCIA ENTRE EL MATERIAL Y EL PRESUPUESTO DEL PROYECTO
                $datosPresupuesto = Presupuesto::where("referencia_material", strtoupper(trim($material["referencia_material"])))
                    ->where("codigo_proyecto", strtoupper(trim($request->codigo_proyecto)))->first();


                if (!$datosPresupuesto) {
                    DB::rollBack();
                    return ResponseHelper::error(422, "El material '{$material["referencia_material"]}' no pertenece al presupesto del proyecto '{$request->codigo_proyecto}'");
                }


                //VALIDO SI LA CANTIDAD A ASIGNAR NO SUPERA A LA CANTIDAD DEL PRESUPUESTO
                if ($datosPresupuesto->cantidad_material < $material["cantidad_material"]) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        422,
                        "El material '{$material["referencia_material"]}' sobre pasa la cantidad del presupuesto"
                    );
                }
                //return $datosPresupuesto->cantidad_material;

                $estadoMaterial = Materiale::where("referencia_material", $material["referencia_material"])
                    ->where("estado", "A")
                    ->first();

                if (!$estadoMaterial) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        404,
                        "El material '{$material["referencia_material"]}' no existe"
                    );
                }

                //OBTENGO EL INVENTARIO DE LA REFERENCIA DEL MATERIAL CON EL CONSECUTIVO
                $inventario = Inventario::where("referencia_material", $material["referencia_material"])
                    ->where("consecutivo", $material["consecutivo"])
                    ->first();
                if (!$inventario) {
                    DB::rollBack();
                    return ResponseHelper::error(404, "No se encontró inventario para el material '{$material["referencia_material"]}' con el consecutivo '{$material["consecutivo"]}'");
                }

                if ($inventario->cantidad < $material["cantidad_material"]) {
                    DB::rollBack();
                    return ResponseHelper::error(400, "No ha suficiente stock para la cantidad requerida del material '{$material["referencia_material"]}'");
                }


                //$inventario->decrement("cantidad", 4);



                Asignacione::create([
                    "inmueble_id" => $request->inmueble_id,
                    "codigo_proyecto" => strtoupper($request->codigo_proyecto),
                    "referencia_material" => $inventario->referencia_material,
                    "costo_material" => $inventario->costo,
                    "consecutivo" => $inventario->consecutivo,
                    "subtotal" => $inventario->costo * $material["cantidad_material"],
                    "cantidad_material" => $material["cantidad_material"],
                    "numero_identificacion" => Auth::user()->numero_identificacion
                ]);
                DB::table('inventarios')
                    ->where("referencia_material", $material["referencia_material"])
                    ->where("consecutivo", $material["consecutivo"])
                    ->decrement("cantidad", $material["cantidad_material"]);
            }

            DB::commit();
            return ResponseHelper::success(201, "Se ha registrado con exito");
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error("Error al registrar asignaciones " . $th);
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function destroy(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            "asignacion_id" => "required|exists:asignaciones,id",
            // 'inmueble_id' => 'required|numeric|exists:inmuebles,id',
            // 'referencia_material' => 'required|string|exists:materiales,referencia_material',
            // 'codigo_proyecto' => 'required|string|exists:proyectos,codigo_proyecto',
            // "consecutivo" => "required|numeric"
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
        }


        try {
            // Buscar la asignación con las claves compuestas
            // $asignacion = Asignacione::where([
            //     'inmueble_id' => (int) $request->inmueble_id,
            //     'referencia_material' => $request->referencia_material,
            //     'codigo_proyecto' => strtoupper($request->codigo_proyecto),
            //     "consecutivo" => $request->consecutivo
            // ])->delete();

            $asignacion = Asignacione::find($request->asignacion_id);



            if (!$asignacion) {

                return ResponseHelper::error(404, "No se encontró la asignación.");
            }

            // Restaurar stock en el inventario
            DB::table('inventarios')
                ->where('referencia_material', $asignacion->referencia_material)
                ->where('consecutivo', $asignacion->consecutivo)
                ->increment('cantidad', $asignacion->cantidad_material);


            
            // Eliminar la asignación
            $asignacion->delete();


            return ResponseHelper::success(200, "La asignación fue eliminada con éxito.");
        } catch (Throwable $th) {

            return ResponseHelper::error(500, "Error interno en el servidor", ["error" => $th->getMessage()]);
        }
    }

    
}
