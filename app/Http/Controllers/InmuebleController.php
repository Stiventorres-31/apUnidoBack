<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inmueble;
use App\Models\Presupuesto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Writer;

class InmuebleController extends Controller
{
    public function index()
    {
        $inmueble = Inmueble::where("estado", "=", "A")->with("usuario", "tipo_inmueble")->get();
        return ResponseHelper::success(201, "Todos los inmuebles", ["inmueble" => $inmueble]);
    }
    public function show($id)
    {
        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $inmueble = Inmueble::find($id);

        return ResponseHelper::success(200, "Se ha encontrado el inmueble", ["inmueble" => $inmueble]);
    }

    public function generateCSV($id)
    {

        $validator = Validator::make(["id" => $id], [
            "id" => "required|exists:inmuebles,id",
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }



        $inmueble = Inmueble::find($id);


        $archivoCSV = Writer::createFromString('');
        $archivoCSV->setDelimiter(";");
        $archivoCSV->setOutputBOM(Writer::BOM_UTF8);
        $archivoCSV->insertOne([
            "codigo_proyecto",
            "inmueble_id",
            "referencia_material",
            "mombre_material",
            "costo del material",
            "Cantidad del material"
        ]);

        foreach ($inmueble->presupuestos as $presupuesto) {
            $archivoCSV->insertOne([
                $presupuesto["codigo_proyecto"],
                $presupuesto["inmueble_id"],
                $presupuesto["referencia_material"],
                $presupuesto["material"]["nombre_material"],
                $presupuesto["costo_material"],
                $presupuesto["cantidad_material"],
            ]);
        }
        // $headers = [
        //     'Content-Type' => 'text/csv',
        //     'Content-Disposition' => 'attachment; filename="reporte_tipo_inmuebles.csv"',
        // ];
        $csvContent = (string) $archivoCSV;
        $filePath = 'reports/reporte_presupuesto.csv';
        Storage::put($filePath, $csvContent);

        return ResponseHelper::success(201, "Se ha generado con exito", ["inmueble" => $filePath]);
    }

    public function store(Request $request)
    {

        $validateData = Validator::make($request->all(), [
            "tipo_inmueble" => "required|exists:tipo_inmuebles,id",
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto",
            "cantidad_inmueble" => "required|numeric"
        ]);


        if ($validateData->fails()) {
            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }

        for ($i = 0; $i < $request->cantidad_inmueble; $i++) {
            $inmueble = new Inmueble();
            $inmueble->tipo_inmueble = strtoupper($request->tipo_inmueble);
            $inmueble->codigo_proyecto = strtoupper($request->codigo_proyecto);
            $inmueble->numero_identificacion = Auth::user()->numero_identificacion;
            $inmueble->save();
        }
        return ResponseHelper::success(201, "Inmueble creado con éxito");
    }

    public function destroy($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:inmuebles,id', // Verifica que exista el ID en la tabla tipo_inmuebles
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $inmueble = Inmueble::find($id);

        $existePresupuesto = Presupuesto::where('inmueble_id', '=', $inmueble->inmueble_id)->exists();
        $existeAsignacion = Asignacione::where('inmueble_id', '=', $inmueble->inmueble_id)->exists();

        if ($inmueble->estado === "F" || $existePresupuesto || $existeAsignacion) {


            return ResponseHelper::error(400, "No se puede eliminar este inmueble. Verifica si el inmueble ya fue finalizado o tenga un presupuesto activo");
        }

        $inmueble->estado = "E";

        return ResponseHelper::success(200, "Se ha eliminado con exito");
    }

    // public function update(Request $request, $id){
    //     $validateData = Validator::make($request->all(), [
    //         "tipo_inmueble" => "required|exists:tipo_inmuebles,id",

    //         "inmueble_id" => "required|unique:inmuebles,inmueble_id|max:255",
    //         'numero_identificacion' => 'required|string|exists:usuarios,numero_identificacion|max:20',
    //     ]);

    //     if ($validateData->fails()) {
    //         return response()->json([
    //             'isError' => true,
    //             'code' => 422,
    //             'message' => 'Verificar la información',
    //             'result' => $validateData->errors(),
    //         ], 422);
    //     }

    //     $inmueble = new Inmueble();


    //     $inmueble->tipo_inmueble = strtoupper($request->tipo_inmueble);
    //     $inmueble->codigo_proyecto = strtoupper($request->codigo_proyecto);
    //     $inmueble->numero_identificacion = strtoupper($request->numero_identificacion);
    //     $inmueble->save();

    //     return response()->json([
    //         'isError' => false,
    //         'code' => 201,
    //         'message' => 'Se ha creado con exito',
    //         'result' => ["inmueble" => $inmueble],
    //     ], 201);
    // }
}
