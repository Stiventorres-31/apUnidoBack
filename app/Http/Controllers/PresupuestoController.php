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
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class PresupuestoController extends Controller
{
    // public function store(Request $request)
    // {
    //     $validatedData = Validator::make($request->all(), [
    //         'nombre_inmueble'         => 'required|exists:inmuebles,nombre_inmueble',
    //         'referencia_material' => 'required|exists:materiales,referencia_material',
    //         'costo_material'      => 'required|numeric|min:1',
    //         'cantidad_material'   => 'required|numeric|min:1',
    //         'codigo_proyecto'     => 'required|exists:proyectos,codigo_proyecto',

    //     ]);

    //     if ($validatedData->fails()) {
    //         return response()->json([
    //             'isError' => true,
    //             'code' => 422,
    //             'message' => 'Verificar la información',
    //             'result' => $validatedData->errors(),
    //         ], 422);
    //     }

    //     // $presupuesto = Presupuesto::create($validatedData);

    //     $requestData = $request->all();


    //     $presupuesto = new Presupuesto();

    //     $dataMaterial = Materiale::where('referencia_material', "=", $request->referencia_material)->first();



    //     $presupuesto->nombre_inmueble = $request->nombre_inmueble;
    //     $presupuesto->referencia_material = $dataMaterial->referencia_material;
    //     $presupuesto->costo_material = $dataMaterial->costo;
    //     $presupuesto->cantidad_material = $request->cantidad;
    //     $presupuesto->codigo_proyecto = strtoupper($request->codigo_proyecto);

    //     $presupuesto->save();

    //     return response()->json([
    //         'isError' => false,
    //         'code' => 201,
    //         'message' => 'Presupuesto creado exitosamente',
    //         'result' => ['presupuesto' => $presupuesto]
    //     ], 201);
    // }
    public function store(Request $request)
    {


        $validatedData = Validator::make($request->all(), [
            'nombre_inmueble' => 'required|exists:inmuebles,nombre_inmueble',
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
                'consecutivo' => "required|numeric|exists:inventarios,consecutivo",
                'cantidad_material'   => 'required|numeric|min:1',
            ]);


            if ($validatedData->fails()) {
                return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
            }

            $exisitencia = Presupuesto::where('referencia_material', $material["referencia_material"])
                ->where("consecutivo", "=", $material["consecutivo"])
                ->where("codigo_proyecto", "=", $request->codigo_proyecto)
                ->where("nombre_inmueble", "=", $request->nombre_inmueble)
                ->first();

            // return response()->json([
            //     'isError' => true,
            //     'code' => 400,
            //     'message' => "Ya existe este material ". $material["referencia_material"] . " con lote ". $material["consecutivo"] ." en el presupuesto",
            //     'result' => $material,
            // ], 400);

            if ($exisitencia) {
                return ResponseHelper::error(400, "Ya existe este material " . $material["referencia_material"] . " con lote " . $material["consecutivo"] . " en el presupuesto");
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
                "nombre_inmueble" => strtoupper($request->nombre_inmueble),
                "codigo_proyecto" => strtoupper($request->codigo_proyecto),
                "referencia_material" => $dataMaterial->referencia_material,
                "consecutivo" => $inventario->consecutivo,
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

    public function destroy(Request $request)
    {


        $validatedData = Validator::make($request->all(), [
            'nombre_inmueble'         => 'required|integer|exists:presupuestos,nombre_inmueble',
            'referencia_material' => 'required|string|exists:presupuestos,referencia_material',
            'codigo_proyecto'     => 'required|string|exists:presupuestos,codigo_proyecto',
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422, $validatedData->errors()->first(), $validatedData->errors());
        }

        $presupuesto = Presupuesto::where([
            'nombre_inmueble' => $request->nombre_inmueble,
            'referencia_material' => $request->referencia_material,
            'codigo_proyecto' => $request->codigo_proyecto,
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
            "nombre_inmueble",
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
                "nombre_inmueble" => "required",
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

            $inmueble = Inmueble::where("nombre_inmueble", trim(strtoupper($valueCSV["nombre_inmueble"])))
                ->where("estado", "A")
                ->first();

            if (!$inmueble) {
                // $datosInmuebles = [
                //     "nombre_inmueble" => trim(strtoupper($valueCSV["nombre_inmueble"])),
                //     "codigo_proyecto" => trim(strtoupper($valueCSV["codigo_proyecto"])),
                //     "tipo_inmueble" => $tipo_inmueble->id,
                //     "numero_identificacion" => Auth::user()->numero_identificacion,
                //     "created_at"=>Carbon::now(),
                //     "updated_at"=>Carbon::now()
                // ];

                $inmueble = new Inmueble();
                $inmueble->nombre_inmueble = trim(strtoupper($valueCSV["nombre_inmueble"]));
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

                "nombre_inmueble" => trim(strtoupper($valueCSV["nombre_inmueble"])),
                "referencia_material" => trim(strtoupper($valueCSV["referencia_material"])),
                "costo_material" => $inventarioMaterial->costo,
                "consecutivo" => $inventarioMaterial->consecutivo,
                "cantidad_material" => $valueCSV["cantidad_material"],
                "subtotal"=>($inventarioMaterial->costo*$valueCSV["cantidad_material"]),
                "codigo_proyecto" => strtoupper($valueCSV["codigo_proyecto"]),
                "numero_identificacion" => auth::user()->numero_identificacion,
                "created_at"=>Carbon::now(),
                "updated_at"=>Carbon::now()
            ];
        }

        Presupuesto::insert($datosPresupuestos);
        

        return ResponseHelper::success(200, "Se ha cargado correctamente", $datosPresupuestos);
    }
}
