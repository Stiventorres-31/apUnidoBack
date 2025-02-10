<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Inmueble;
use App\Models\Inventario;
use App\Models\Materiale;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\TipoInmueble;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use League\Csv\Writer;

class PresupuestoController extends Controller
{
    public function store(Request $request)
    {


        $validatedData = Validator::make($request->all(), [
            'inmueble_id' => 'required|exists:inmuebles,id',
            'codigo_proyecto' => 'required|exists:proyectos,codigo_proyecto',
            'materiales' => "required|array",

        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
        }

        $numero_identificacion = Auth::user()->numero_identificacion;
        $templatePresupuesto = [];
        DB::beginTransaction();
        foreach ($request->materiales as $material) {
            $validatedData = Validator::make($material, [
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
                // 'costo_material' => 'required',
                "consecutivo" => "required",
                'cantidad_material'   => 'required|numeric|min:1',
            ]);


            if ($validatedData->fails()) {
                DB::rollBack();
                return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
            }
            $inmueble = Inmueble::where("id",$request->inmueble_id)
            ->where("estado","A")
            ->first();
            if ($inmueble->codigo_proyecto !== $request->codigo_proyecto) {
                DB::rollBack();
                return ResponseHelper::error(
                    400,
                    "El inmueble '{$request->inmueble_id}' no pertenece al proyecto '{$request->codigo_proyecto}'"
                );
            }

            $exisitencia = Presupuesto::where('referencia_material', $material["referencia_material"])

                ->where("codigo_proyecto",  $request->codigo_proyecto)
                ->where("inmueble_id", $request->inmueble_id)
                ->first();


            if ($exisitencia) {
                DB::rollBack();
                return ResponseHelper::error(400, "Ya existe este material "
                    . $material["referencia_material"]);
            }

            $dataMaterial = Materiale::where(
                'referencia_material',
                strtoupper($material["referencia_material"])
            )
                ->first();

            if ($dataMaterial->estado !== "A" || !$dataMaterial) {
                DB::rollBack();
                return ResponseHelper::error(404, "Este material no existe con código => " . $material["referencia_material"]);
            }


            $inventario = Inventario::where("referencia_material",  $dataMaterial->referencia_material)
                ->where("consecutivo", $material["consecutivo"])->first();
            if (!$inventario) {
                DB::rollBack();
                return ResponseHelper::error(404, "El lote {$material["consecutivo"]} de este material {$material["referencia_material"]} no existe");
            }

            

            Presupuesto::create([
                "inmueble_id" => strtoupper($request->inmueble_id),
                "codigo_proyecto" => strtoupper($request->codigo_proyecto),
                "referencia_material" => $dataMaterial->referencia_material,
                "costo_material" => $inventario->costo,
                "cantidad_material" => $material["cantidad_material"],
                "subtotal" => floatval($inventario->costo * $material["cantidad_material"]),

                "numero_identificacion" => $numero_identificacion
            ]);
        }

        // return response()->json($templatePresupuesto);
        //Presupuesto::insert($templatePresupuesto);
        DB::commit();
        return ResponseHelper::success(201, "Se ha creado con exito");
    }

    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "codigo_proyecto" => "required|exists:presupuestos,codigo_proyecto",
            "inmueble_id" => "required|exists:presupuestos,inmueble_id",
            "referencia_material" => "required|exists:presupuestos,referencia_material"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $presupuesto = Presupuesto::where("codigo_proyecto", $request->codigo_proyecto)
            ->where("inmueble_id", $request->inmueble_id)
            ->where("referencia_material", $request->referencia_material)
            ->first();



        return ResponseHelper::success(200, "Se ha obtenido con exito", ["presupuesto" => $presupuesto]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto",
            "inmueble_id" => "required|exists:inmuebles,id",
            "referencia_material" => "required|exists:materiales,referencia_material",
            "cantidad_material" => "required|numeric"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $presupuesto = Presupuesto::where("codigo_proyecto", $request->codigo_proyecto)
            ->where("inmueble_id", $request->inmueble_id)
            ->where("referencia_material", $request->referencia_material)
            ->first();

        if (!$presupuesto) {
            return ResponseHelper::error(404, "El presupuesto con los datos proporcionados no existe.");
        }
        $presupuesto->cantidad_material = $request->cantidad_material;
        $presupuesto->subtotal = $presupuesto->costo_material * $request->cantidad_material;
        $presupuesto->save();

        return ResponseHelper::success(200, "Se ha actualizado con exito");
    }

    public function destroy(Request $request)
    {


        $validatedData = Validator::make($request->all(), [
            'inmueble_id'         => 'required|integer|exists:inmuebles,id',
            'referencia_material' => 'required|string|exists:materiales,referencia_material',
            'codigo_proyecto'     => 'required|string|exists:proyectos,codigo_proyecto',
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
        }

        $presupuesto = Presupuesto::where([
            'inmueble_id' => $request->inmueble_id,
            'referencia_material' => $request->referencia_material,
            'codigo_proyecto' => $request->codigo_proyecto
        ])->delete();

        if (!$presupuesto) {
            return ResponseHelper::error(404, "Presupuesto no encontrado");
        }


        // $presupuesto->delete(); 



        return ResponseHelper::success(200, "Se ha eliminado con exito");
    }

    public function fileMasivo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            "codigo_proyecto" => "required"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        $cabecera = [
            "codigo_inmueble",
            "referencia_material",
            "costo_material",
            "cantidad_material"
        ];

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $archivoCSV = Reader::createFromPath($filePath, "r");
        $archivoCSV->setDelimiter(';');;
        $archivoCSV->setHeaderOffset(0); //obtenemos la cabecera


        $archivoCabecera = $archivoCSV->getHeader();

        // return $archivoCabecera;

        if ($archivoCabecera !== $cabecera) {
            return ResponseHelper::error(422, "El archivo no tiene la estructura requerida");
        }


        $datosPresupuestos = [];
        $datosInmuebles = [];


        // Iniciar una transacción
        DB::beginTransaction();
        foreach ($archivoCSV->getRecords() as $valueCSV) {
            $validatorDataCSV = Validator::make($valueCSV, [
                "codigo_inmueble" => "required",
                "costo_material" => "required",
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
                "cantidad_material" => "required",

            ]);

            if ($validatorDataCSV->fails()) {
                DB::rollBack();
                return ResponseHelper::error(422, $validatorDataCSV->errors()->first(), $validatorDataCSV->errors());
            }

            // $tipo_inmueble = TipoInmueble::where("nombre_tipo_inmueble",  trim(strtoupper($valueCSV["tipo_inmueble"])))
            //     ->where("estado", "A")
            //     ->first();

            // if (!$tipo_inmueble) {
            //     DB::rollBack();
            //     return ResponseHelper::error(404, "El tipo de inmueble '{$tipo_inmueble}' no existe");
            // }

            // $inventarioMaterial = Inventario::where("referencia_material", trim(strtoupper($valueCSV["referencia_material"])))
            //     ->where("consecutivo", (int) $valueCSV["consecutivo"])
            //     ->where("estado", "A")
            //     ->first();

            // if (!$inventarioMaterial) {
            //     return ResponseHelper::error(404, "El material '{$valueCSV["referencia_material"]}' con el lote '{$valueCSV["consecutivo"]}' no existe");
            // }

            $existencia_presupuesto = Presupuesto::where("inmueble_id", $valueCSV["codigo_inmueble"])
                ->where("codigo_proyecto", $request->codigo_proyecto)
                ->where("referencia_material", $valueCSV["referencia_material"])->exists();

            if ($existencia_presupuesto) {
                DB::rollBack();
                return ResponseHelper::error(
                    400,
                    "El presupuesto del inmueble '{$valueCSV['codigo_inmueble']}' con el material '{$valueCSV['referencia_material']}' ya existe en el proyecto '{$request->codigo_proyecto}'"
                );
            }
            $inmueble = Inmueble::where("id", trim(strtoupper($valueCSV["codigo_inmueble"])))
                ->where("estado", "A")
                ->first();

            if (!$inmueble) {
                DB::rollBack();
                return ResponseHelper::error(
                    400,
                    "El inmueble '{$valueCSV['codigo_inmueble']}' no existe"
                );

                // $inmueble = new Inmueble();
                // // $inmueble->id = trim(strtoupper($valueCSV["codigo_inmueble"]));
                // $inmueble->codigo_proyecto = trim(strtoupper($valueCSV["codigo_proyecto"]));
                // $inmueble->tipo_inmueble = $tipo_inmueble->id;

                // $inmueble->numero_identificacion = Auth::user()->numero_identificacion;
                // $inmueble->save();
            }

            if ($inmueble->codigo_proyecto !== $request->codigo_proyecto) {
                DB::rollBack();
                return ResponseHelper::error(
                    400,
                    "El inmueble '{$valueCSV['codigo_inmueble']}' no pertenece al proyecto '{$request->codigo_proyecto}'"
                );
            }


            Presupuesto::create(
                [

                    "inmueble_id" => $inmueble->id,
                    "referencia_material" => trim(strtoupper($valueCSV["referencia_material"])),
                    "costo_material" => $valueCSV["costo_material"],
                    "cantidad_material" => $valueCSV["cantidad_material"],
                    "subtotal" => ($valueCSV["costo_material"] * $valueCSV["cantidad_material"]),
                    "codigo_proyecto" => $request->codigo_proyecto,
                    "numero_identificacion" => auth::user()->numero_identificacion,
                    // "created_at" => Carbon::now(),
                    // "updated_at" => Carbon::now()
                ]
            );


            // $datosPresupuestos[] = [

            //     "inmueble_id" => $inmueble->id,
            //     "referencia_material" => trim(strtoupper($valueCSV["referencia_material"])),
            //     "costo_material" => $valueCSV["costo_material"],
            //     "cantidad_material" => $valueCSV["cantidad_material"],
            //     "subtotal" => ($valueCSV["costo_material"] * $valueCSV["cantidad_material"]),
            //     "codigo_proyecto" => strtoupper($valueCSV["codigo_proyecto"]),
            //     "numero_identificacion" => auth::user()->numero_identificacion,
            //     "created_at" => Carbon::now(),
            //     "updated_at" => Carbon::now()
            // ];
        }

        //Presupuesto::insert($datosPresupuestos);
        DB::commit();

        return ResponseHelper::success(200, "Se ha cargado correctamente", $datosPresupuestos);
    }

    public function generateCSV($codigo_proyecto)
    {

        $proyecto = Proyecto::with(['inmuebles.presupuestos', "inmuebles.asignaciones"])->find($codigo_proyecto);

        if (!$proyecto) {


            return ResponseHelper::error(404, "El proyecto no existe");
        }

        // return response()->json(
        //     [
        //         "presupuesto" => count($proyecto->inmuebles),
        //         "proyecto" =>    $proyecto
        //     ]
        // );

        $archivoCSV = Writer::createFromString("");
        $archivoCSV->setDelimiter(";");
        $archivoCSV->setOutputBOM(Writer::BOM_UTF8);
        $archivoCSV->insertOne([
            "Código del proyecto",
            "Departamento",
            "Ciudad",
            "Dirección",
            "Fecha de inicio",
            "Fecha de finalización",
            "valorización",
            //  "Progreso Total"
        ]);
        $total_subtotal_presupuesto = collect($proyecto->inmuebles)
            ->flatMap(fn($inmueble) => $inmueble->presupuestos)
            ->sum(fn($presupuesto) => floatval($presupuesto->subtotal));

        $total_presupuestado = 0;
        $total_asignado = 0;

        // Recorrer todos los inmuebles del proyecto
        foreach ($proyecto->inmuebles as $inmueble) {

            // Calcular la cantidad total presupuestada
            foreach ($inmueble->presupuestos as $presupuesto) {
                $total_presupuestado += floatval($presupuesto->cantidad_material);
            }

            // Calcular la cantidad total asignada
            foreach ($inmueble->asignaciones as $asignacion) {
                $total_asignado += floatval($asignacion->cantidad_material);
            }
        }

        // Evitar división por cero
        $progreso = $total_presupuestado > 0
            ? ($total_asignado / $total_presupuestado) * 100
            : 0;
        $archivoCSV->insertOne([
            $proyecto->codigo_proyecto,
            $proyecto->departamento_proyecto,
            $proyecto->ciudad_municipio_proyecto,
            $proyecto->direccion_proyecto,
            $proyecto->fecha_inicio_proyecto,
            $proyecto->fecha_final_proyecto,
            number_format($total_subtotal_presupuesto, 2),
            // $progreso
        ]);
        $archivoCSV->insertOne([]);
        $archivoCSV->insertOne([
            "Codigo del inmueble",
            "Referencia del material",
            "Nombre_material",
            "Costo del material",
            "Cantidad del material"
        ]);

        foreach ($proyecto->inmuebles as $inmueble) {
            foreach ($inmueble->presupuestos as $presupuesto) {
                $archivoCSV->insertOne([
                    $presupuesto->inmueble_id,
                    $presupuesto->referencia_material,
                    $presupuesto->material->nombre_material,
                    $presupuesto->costo_material,
                    $presupuesto->cantidad_material,
                ]);
            }
        }
        // $headers = [
        //     'Content-Type' => 'text/csv',
        //     'Content-Disposition' => 'attachment; filename="reporte_tipo_inmuebles.csv"',
        // ];
        $csvContent = (string) $archivoCSV;
        $filePath = 'reports/reporte_presupuesto.csv';
        Storage::put($filePath, $csvContent);

        return ResponseHelper::success(201, "El reporte se ha generado y guardado correctamente", ["proyecto" => $filePath]);
    }
}
