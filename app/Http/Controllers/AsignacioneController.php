<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Materiale;
use App\Models\Presupuesto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AsignacioneController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'nombre_inmueble'          => 'required|integer|exists:inmuebles,nombre_inmueble',
            "materiales" => "required|array",
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422,$validatedData->errors()->first(),$validatedData->errors());
        }

        $templateAsingacion=[];
        foreach ($request->materiales as $material) {
            $validatedData = Validator::make($material, [
                'referencia_material'  => 'required|string|max:10|exists:materiales,referencia_material',
                'codigo_proyecto'      => 'required|string|max:255|exists:proyectos,codigo_proyecto',
                'cantidad_material'    => 'required|numeric',
                'costo_material'       => 'required|numeric',
            ]);

            if ($validatedData->fails()) {
                return ResponseHelper::error(422,$validatedData->errors()->first(),$validatedData->errors());
            }
            // Obtener datos del material
            $dataMaterial = Materiale::where('referencia_material', $request->referencia_material)->first();

            // if (!$dataMaterial) {
            //     return response()->json([
            //         'isError' => true,
            //         'code' => 404,
            //         'message' => 'Material no encontrado',
            //         'result' => []
            //     ], 404);
            // }

            // Obtener datos del presupuesto
            $dataPresupuesto = Presupuesto::where([
                'nombre_inmueble' => strtoupper($material->nombre_inmueble),
                'referencia_material' => $material["referencia_material"],
                'codigo_proyecto' => strtoupper($material["codigo_proyecto"]),
            ])->first();

            if (!$dataPresupuesto) {
                return ResponseHelper::error(404,"Asingación no encontrada",["asignacion" => $material]);
               
            }
            // Validar stock de material disponible
            if ($dataMaterial->cantidad < $material["cantidad_material"]) {
              
                return ResponseHelper::error(401,"No está disponible la cantidad que requieres",["asignacion" => $material]);
            }


            // Validar si la cantidad sobrepasa el presupuesto
            if ($dataPresupuesto->cantidad_material < $material["cantidad_material"]) {
               

                return ResponseHelper::error(401,"La cantidad sobrepasa el presupuesto",["asignacion" => $material]);
            }
            $templateAsingacion[] = [
                "nombre_inmueble" => strtoupper($request->nombre_inmueble),
                "codigo_proyecto" => strtoupper($material["codigo_proyecto"]),
                "referencia_material" => $dataMaterial->referencia_material,
                "costo_material" => $dataMaterial->costo,
                "cantidad_material" => $material["cantidad_material"],
                "numero_identificacion" => Auth::user()->numero_identificacion,
                "estado" => "A",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            // Asignacione::firstOrCreate([
            //     "nombre_inmueble" => strtoupper($request->nombre_inmueble),
            //     "codigo_proyecto" => strtoupper($material["codigo_proyecto"]),
            //     "referencia_material" => $dataMaterial->referencia_material,
            //     "costo_material" => $dataMaterial->costo,
            //     "cantidad_material" => $material["cantidad_material"],
            //     "numero_identificacion" => Auth::user()->numero_identificacion,
            //     "estado" => "A"

            // ]);
        }




        // Crear asignación
        // $asignacion = new Asignacione();

        // $asignacion->numero_identificacion = $request->numero_identificacion;
        // $asignacion->referencia_material = strtoupper($request->referencia_material);
        // $asignacion->nombre_inmueble = strtoupper($request->nombre_inmueble);
        // $asignacion->codigo_proyecto = strtoupper($request->codigo_proyecto);
        // $asignacion->cantidad_material = $request->cantidad_material;
        // $asignacion->costo_material = $request->costo_material;
        // $asignacion->estado = "A";
        // $asignacion->save();

        // // Actualizar la cantidad de material disponible
        // $dataMaterial->cantidad = $dataMaterial->cantidad - $request->cantidad_material;
        // $dataMaterial->save();

        Asignacione::insert($templateAsingacion);
        return ResponseHelper::success(201,"Se ha registrado con exito");
    }

    public function destroy(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'nombre_inmueble'         => 'required|string|exists:,nombre_inmueble',
            'referencia_material' => 'required|string|exists:materiales,referencia_material',
            'codigo_proyecto'     => 'required|string|exists:proyectos,codigo_proyecto'
        ]);

        if ($validatedData->fails()) {
            return ResponseHelper::error(422,$validatedData->errors()->first(),$validatedData->errors());
        }

        $dataAsignacion = Asignacione::where([
            'nombre_inmueble' => strtoupper($request->nombre_inmueble),
            'referencia_material' => $request->referencia_material,
            'codigo_proyecto' => strtoupper($request->codigo_proyecto),
        ])->first();


        $dataAsignacion->estado = "E";

        $material = Materiale::find($request->referencia_material);

        $material->cantidad += $dataAsignacion->cantidad_material;
        $material->save();

        return ResponseHelper::success(200,"Se ha eliminado con exito");
    }
}
