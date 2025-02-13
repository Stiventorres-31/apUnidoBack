<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inmueble;
use App\Models\Inventario;
use App\Models\Materiale;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
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
                    "consecutivo" => "required|size:0",
                    "cantidad_material"    => "required|numeric|size:0",
                    "costo_material"       => "required|numeric|size:0",
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
            "asignacion_id" => "required|exists:asignaciones,id"
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

    public function fileMasivo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "file" => "required|file",
            "codigo_proyecto" => "required|exists:asignaciones,codigo_proyecto"
        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $cabecera = [
            "inmueble_id",
            "referencia_material",
            "consecutivo",
            "cantidad_material"
        ];

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $archivoCSV = Reader::createFromPath($filePath, "r");
        $archivoCSV->setDelimiter(';');;
        $archivoCSV->setHeaderOffset(0); //obtenemos la cabecera


        $archivoCabecera = $archivoCSV->getHeader();

        if ($archivoCabecera !== $cabecera) {
            return ResponseHelper::error(422, "El archivo no tiene la estructura requerida");
        }


        try {
            DB::beginTransaction();
            foreach ($archivoCSV->getRecords() as $datoAsignacionCSV) {
                $validatorDataCSV = Validator::make($datoAsignacionCSV, [
                    "inmueble_id" => "required",
                    "consecutivo" => "required|min:1",
                    "referencia_material" => [
                        "required",
                        function ($attribute, $value, $fail) {
                            $referencia_material = strtoupper($value);
                            if (!Materiale::where("referencia_material",  $referencia_material)
                                ->where("estado", "A")
                                ->exists()) {
                                $fail("La referencia del material '{$referencia_material}' no existe");
                            }
                        }
                    ],
                    "cantidad_material" => "required|numeric|min:1",
                ]);

                if ($validatorDataCSV->fails()) {
                    DB::rollBack();
                    return ResponseHelper::error(422, $validatorDataCSV->errors()->first(), $validatorDataCSV->errors());
                }

                $proyecto = Proyecto::find(strtoupper(trim($request->codigo_proyecto)))->exists();
                if (!$proyecto) {
                    DB::rollback();
                    return ResponseHelper::error(404, "El proyecto no existe");
                }
                $presupuesto = Presupuesto::where("referencia_material", $datoAsignacionCSV["referencia_material"])
                    ->where("codigo_proyecto", $request->codigo_proyecto)
                    ->where("inmueble_id", $datoAsignacionCSV["inmueble_id"])
                    ->first();

                if (!$presupuesto) {
                    DB::rollBack();
                    return ResponseHelper::error(404, "No existe prespuesto para este inmueble '{$datoAsignacionCSV["inmueble_id"]}' con este material '{$datoAsignacionCSV["referencia_material"]}'");
                }
                $inventario = Inventario::where("referencia_material", $presupuesto->referencia_material)
                    ->where("consecutivo", $datoAsignacionCSV["consecutivo"])->first();

                if (!$inventario) {
                    DB::rollBack();
                    return ResponseHelper::error(
                        404,
                        "No existe lote de este '{$datoAsignacionCSV["referencia_material"]}' con lote '{$datoAsignacionCSV["consecutivo"]}'"
                    );
                }
                $inmueble = Inmueble::where("id", $datoAsignacionCSV["inmueble_id"])
                    ->where("codigo_proyecto", $request->codigo_proyecto)
                    ->first();

                if (!$inmueble) {
                    DB::rollBack();
                    return ResponseHelper::error(404, "El inmueble '{$datoAsignacionCSV["inmueble_id"]}' no existe para este proyecto '{$request->codigo_proyecto}'");
                }

                //CALCULAR SI LA CANTIDAD ACTUAL Y LA ASIGNAR NO SUPERA A LA DEL PRESUPUESTO

                $cantidadAsignadoActualmente = Asignacione::where("referencia_material", $inventario->referencia_material)
                    ->where("inmueble_id", $datoAsignacionCSV["inmueble_id"])
                    ->where("consecutivo", $inventario->consecutivo)
                    ->max("cantidad_material") ?? 0;

                $calcularCantidadTotal = $cantidadAsignadoActualmente +$datoAsignacionCSV["cantidad_material"];

                if($calcularCantidadTotal>$presupuesto->cantidad_material){
                    DB::rollback();
                    return ResponseHelper::error(400,
                    "La cantidad a asignar del material '{$datoAsignacionCSV["referencia_material"]}' 
                    al inmueble '{$datoAsignacionCSV["inmueble_id"]}' supera el stock del presupuesto");
                }

                return "firme";
                // if()
                return [
                    "proyecto" => $proyecto,
                    "presupuesto" => $presupuesto,
                    "inventario" => $inventario,
                    "inmueble" => $inmueble,
                ];

                Asignacione::create([
                    "inmueble_id" => $datoAsignacionCSV["inmueble_id"],
                    "referencia_material" => $inventario->referencia_material,
                    "consecutivo" => $inventario->consecutivo,
                    "costo_material" => $inventario->consecutivo,
                    "cantidad_material" => $datoAsignacionCSV["cantidad_material"],
                    "subtotal" => $inventario->costo * $datoAsignacionCSV["cantidad_material"],
                    "codigo_proyecto" => $proyecto->codigo_proyecto,
                ]);
            }

            DB::commit();
            return responseHelper::success(201, "Se han creado con exito");
        } catch (Throwable $th) {
            DB::rollback();
            Log::error("Error al registrar las asignaciones masivamente " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "asignacion_id" => "required|exists:asignaciones,id",
            "cantidad_material" => "required|numeric|size:0",
            "accion" => "required"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $asignacion = Asignacione::find($request->asignacion_id);


            if ($request->accion === "restar") {

                if ($asignacion->cantidad_material <= $request->cantidad_material) {

                    $asignacion->cantidad_material -= $request->cantidad_material;
                    if ($asignacion->cantidad_material < 0) {
                        return ResponseHelper::error(400, "La cantidad es menor a 0, por favor ingrese la cantidad correcta");
                    }

                    DB::table('inventarios')
                        ->where('referencia_material', $asignacion->referencia_material)
                        ->where('consecutivo', $asignacion->consecutivo)
                        ->increment('cantidad', $request->cantidad_material);
                } else {
                    return ResponseHelper::error(400, "La cantidad a disminuir es mayor al stock actual");
                }
            } else {
                $presupuesto = Presupuesto::where("referencia_material", $asignacion->referencia_material)
                    ->where("codigo_proyecto", $asignacion->codigo_proyecto)
                    ->where("inmueble_id", $asignacion->inmueble_id)
                    ->first();

                $sumaCantidadAcualCantidadNuevo = $asignacion->cantidad_material +  $request->cantidad_material;


                if ($sumaCantidadAcualCantidadNuevo >= $presupuesto->cantidad_material) {
                    return ResponseHelper::error(400, "La suma supera el stock presupuestado");
                }

                //ME FALTA VALIDAR SI EL INVENTARIO A INGRESAR + EL ACTUAL SUPERA EL STOCK DEL PRESUPUESTO  

                if ($asignacion->cantidad_material <= $request->cantidad_material) {

                    $asignacion->cantidad_material += $request->cantidad_material;

                    DB::table('inventarios')
                        ->where('referencia_material', $asignacion->referencia_material)
                        ->where('consecutivo', $asignacion->consecutivo)
                        ->decrement('cantidad', $request->cantidad_material);
                } else {
                    return ResponseHelper::error(401, "La cantidad a disminuir es mayor al stocl actual");
                }
            }
            $asignacion->save();

            return ResponseHelper::success(200, "Se ha actualizado con exito");
        } catch (Throwable $th) {
            Log::error("Error al actualizar el asignacion " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
