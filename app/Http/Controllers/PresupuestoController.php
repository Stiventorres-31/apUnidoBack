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
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

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

        foreach ($request->materiales as $material) {
            $validatedData = Validator::make($material, [
                'referencia_material' => 'required|exists:materiales,referencia_material',
                'costo_material'      => 'required|numeric|min:1',
                "consecutivo" => "required",
                'cantidad_material'   => 'required|numeric|min:1',
            ]);


            if ($validatedData->fails()) {
                return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
            }

            $exisitencia = Presupuesto::where('referencia_material', $material["referencia_material"])

                ->where("codigo_proyecto", "=", $request->codigo_proyecto)
                ->where("inmueble_id", "=", $request->inmueble_id)
                ->first();


            if ($exisitencia) {
                return ResponseHelper::error(400, "Ya existe este material "
                    . $material["referencia_material"]);
            }

            $dataMaterial = Materiale::where(
                'referencia_material',
                "=",
                strtoupper($material["referencia_material"])
            )
                ->first();

            if ($dataMaterial->estado !== "A" || !$dataMaterial) {

                return ResponseHelper::error(404, "Este material no existe con código = > " . $material->referencia_material);
            }

            $inventario = Inventario::where("referencia_material", "=", $dataMaterial->referencia_material)
                ->where("consecutivo", "=", $material["consecutivo"])->first();


            $templatePresupuesto[] = [
                "inmueble_id" => strtoupper($request->inmueble_id),
                "codigo_proyecto" => strtoupper($request->codigo_proyecto),
                "referencia_material" => $dataMaterial->referencia_material,
                "costo_material" => $inventario->costo,
                "cantidad_material" => $material["cantidad_material"],
                "subtotal" => floatval($inventario->costo * $material["cantidad_material"]),

                "numero_identificacion" => $numero_identificacion,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // return response()->json($templatePresupuesto);
        Presupuesto::insert($templatePresupuesto);

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
            'file' => 'required|file'
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        $cabecera = [
            "codigo_inmueble",
            "tipo_inmueble",
            "referencia_material",
            "consecutivo",
            "cantidad_material",
            "codigo_proyecto"
        ];

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $archivoCSV = Reader::createFromPath($filePath, "r");
        $archivoCSV->setDelimiter(';');;
        $archivoCSV->setHeaderOffset(0); //obtenemos la cabecera


        $archivoCabecera = $archivoCSV->getHeader();


        if ($archivoCabecera !== $cabecera) {
            return ResponseHelper::error(400, "El archivo no tiene la estructura requerida");
        }


        $datosPresupuestos = [];
        $datosInmuebles = [];



        foreach ($archivoCSV->getRecords() as $valueCSV) {
            $validatorDataCSV = Validator::make($valueCSV, [
                "codigo_inmueble" => "required",
                "tipo_inmueble" => "required",
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
                "codigo_proyecto" => [
                    "required",
                    function ($attribute, $value, $fail) {
                        $codigo_proyecto = strtoupper($value);
                        if (!Proyecto::where("codigo_proyecto", $codigo_proyecto)
                            ->where("estado", "A")
                            ->exists()) {
                            $fail("El código del proyecto no existe '{$codigo_proyecto}' no existe");
                        }
                    }
                ]
            ]);

            if ($validatorDataCSV->fails()) {
                return ResponseHelper::error(422, $validatorDataCSV->errors()->first(), $validatorDataCSV->errors());
            }

            $tipo_inmueble = TipoInmueble::where("nombre_tipo_inmueble",  trim(strtoupper($valueCSV["tipo_inmueble"])))
                ->where("estado", "A")
                ->first();
            if (!$tipo_inmueble) {
                return ResponseHelper::error(404, "El tipo de inmueble '{$tipo_inmueble}' no existe");
            }

            $inmueble = Inmueble::where("codigo_inmueble", trim(strtoupper($valueCSV["codigo_inmueble"])))
                ->where("estado", "A")
                ->first();

            if (!$inmueble) {
                // $datosInmuebles = [
                //     "codigo_inmueble" => trim(strtoupper($valueCSV["codigo_inmueble"])),
                //     "codigo_proyecto" => trim(strtoupper($valueCSV["codigo_proyecto"])),
                //     "tipo_inmueble" => $tipo_inmueble->id,
                //     "numero_identificacion" => Auth::user()->numero_identificacion,
                //     "created_at"=>Carbon::now(),
                //     "updated_at"=>Carbon::now()
                // ];

                $inmueble = new Inmueble();
                $inmueble->codigo_inmueble = trim(strtoupper($valueCSV["codigo_inmueble"]));
                $inmueble->codigo_proyecto = trim(strtoupper($valueCSV["codigo_proyecto"]));
                $inmueble->tipo_inmueble = $tipo_inmueble->id;

                $inmueble->numero_identificacion = Auth::user()->numero_identificacion;
                $inmueble->save();
            }

            $inventarioMaterial = Inventario::where("referencia_material", trim(strtoupper($valueCSV["referencia_material"])))
                ->where("consecutivo", (int) $valueCSV["consecutivo"])
                ->where("estado", "A")
                ->first();

            if (!$inventarioMaterial) {
                return ResponseHelper::error(404, "El material '{$valueCSV["referencia_material"]}' con el lote '{$valueCSV["consecutivo"]}' no existe");
            }

            $datosPresupuestos[] = [

                "codigo_inmueble" => trim(strtoupper($valueCSV["codigo_inmueble"])),
                "referencia_material" => trim(strtoupper($valueCSV["referencia_material"])),
                "costo_material" => $inventarioMaterial->costo,
                "consecutivo" => $inventarioMaterial->consecutivo,
                "cantidad_material" => $valueCSV["cantidad_material"],
                "subtotal" => ($inventarioMaterial->costo * $valueCSV["cantidad_material"]),
                "codigo_proyecto" => strtoupper($valueCSV["codigo_proyecto"]),
                "numero_identificacion" => auth::user()->numero_identificacion,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
            ];
        }

        Presupuesto::insert($datosPresupuestos);


        return ResponseHelper::success(200, "Se ha cargado correctamente", $datosPresupuestos);
    }
}
