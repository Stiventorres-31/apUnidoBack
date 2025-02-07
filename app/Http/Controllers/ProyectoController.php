<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Writer;

class ProyectoController extends Controller
{
    public function index()
    {



        $proyectos = DB::table('proyectos')
            ->leftJoin('presupuestos', 'proyectos.codigo_proyecto', '=', 'presupuestos.codigo_proyecto')
            ->select(
                "proyectos.id",
                "proyectos.codigo_proyecto",
                "proyectos.departamento_proyecto",
                "proyectos.ciudad_municipio_proyecto",
                "proyectos.direccion_proyecto",
                "proyectos.numero_identificacion",
                "fecha_inicio_proyecto",
                "fecha_final_proyecto",
                "proyectos.estado",
                DB::raw('COALESCE(SUM(presupuestos.subtotal), 0) as total_presupuesto') // Si no hay presupuesto, devuelve 0
            )
            ->groupBy(
                'proyectos.codigo_proyecto',
                'proyectos.departamento_proyecto',
                'proyectos.ciudad_municipio_proyecto',
                'proyectos.direccion_proyecto',
                'proyectos.numero_identificacion',
                'proyectos.estado'
            )
            ->where("estado","=","A")->get();
        return ResponseHelper::success(200, "Listado de proyectos", ["proyectos" => $proyectos]);
    }


    public function store(Request $request)
    {
        $validateData = Validator::make($request->all(), [
            "codigo_proyecto" => "required|unique:proyectos,codigo_proyecto|min:3",
            "departamento_proyecto" => "required|min:6",
            "ciudad_municipio_proyecto" => "required|min:6",
            "direccion_proyecto" => "required|min:6",

            "fecha_inicio_proyecto" => "required|date_format:Y-m-d",
            "fecha_final_proyecto" => "required|date_format:Y-m-d|after:fecha_inicio_proyecto"
        ]);

        if ($validateData->fails()) {
            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }

        $proyecto = new Proyecto();
        $proyecto->codigo_proyecto = strtoupper($request->codigo_proyecto);
        $proyecto->departamento_proyecto = strtoupper($request->departamento_proyecto);
        $proyecto->ciudad_municipio_proyecto = strtoupper($request->ciudad_municipio_proyecto);
        $proyecto->direccion_proyecto = strtoupper($request->direccion_proyecto);
        $proyecto->numero_identificacion = Auth::user()->numero_identificacion;
        $proyecto->fecha_inicio_proyecto = $request->fecha_inicio_proyecto;
        $proyecto->fecha_final_proyecto = $request->fecha_final_proyecto;
        // $proyecto->fecha_inicio_proyecto = Carbon::parse("d/m/Y", $request->fecha_inicio_proyecto)->format("Y-m-d");
        // $proyecto->fecha_final_proyecto = Carbon::parse("d/m/Y", $request->fecha_final_proyecto)->format("Y-m-d");
        $proyecto->estado = "A";
        $proyecto->save();

        return ResponseHelper::success(201, "Se ha creado con exito", ["proyecto" => $proyecto]);
    }
    public function showWithPresupuesto($codigo_proyecto)
    {

        $proyecto = Proyecto::with('inmuebles.presupuestos')->find($codigo_proyecto);

        if (!$proyecto) {
            return ResponseHelper::error(404, "Proyecto no encontrado");
        }


        $proyectoArray = $proyecto->toArray();


        foreach ($proyectoArray['inmuebles'] as &$inmueble) {
            $inmueble['total_presupuesto'] = collect($inmueble['presupuestos'])->sum('subtotal');
            // unset($inmueble['presupuestos']);
        }

        return ResponseHelper::success(200, "Proyecto obtenido", ["proyecto" => $proyectoArray]);


        // $proyecto = Proyecto::with('inmuebles.presupuestos')->find($codigo_proyecto);

        // if (!$proyecto) {
        //     return ResponseHelper::error(404, "Proyecto no encontrado");
        // }


        // $totalPresupuesto = $proyecto->inmuebles->flatMap(fn($inmueble) => $inmueble->presupuestos)->sum(fn($presupuesto) => $presupuesto->subtotal);

        // return ResponseHelper::success(200, "Proyecto obtenido", 
        //     ["proyecto" =>  ["total_presupuesto" => $totalPresupuesto]+$proyecto->toArray()]
        // );

    }
    public function show($codigo_proyecto)
    {

        $proyecto = Proyecto::with('inmuebles.presupuestos')->find($codigo_proyecto);

        if (!$proyecto) {
            return ResponseHelper::error(404, "Proyecto no encontrado");
        }


        $proyectoArray = $proyecto->toArray();


        foreach ($proyectoArray['inmuebles'] as &$inmueble) {
            $inmueble['total_presupuesto'] = collect($inmueble['presupuestos'])->sum('subtotal');
            unset($inmueble['presupuestos']);
        }

        return ResponseHelper::success(200, "Proyecto obtenido", ["proyecto" => $proyectoArray]);
    }

    public function update(Request $request, $codigo_proyecto)
    {
        $proyecto = Proyecto::find(strtoupper(trim($codigo_proyecto)));

        if (!$proyecto) {

            return ResponseHelper::error(404, "Proyecto no encontrado");
        }
        if ($proyecto->estado === 'F') {
            // Código 403 para indicar acción prohibida
            return ResponseHelper::error(403, "Este proyecto no se puede actualizar");
        }

        $validator = Validator::make($request->all(), [

            'departamento_proyecto' => 'sometimes|min:6',
            'ciudad_municipio_proyecto' => 'sometimes|min:6',
            'direccion_proyecto' => 'sometimes|min:6',

            "fecha_inicio_proyecto" => "required|date_format:Y-m-d",
            "fecha_final_proyecto" => "required|date_format:Y-m-d|after:fecha_inicio_proyecto"

        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $proyecto->departamento_proyecto = strtoupper($request->departamento_proyecto);
        $proyecto->ciudad_municipio_proyecto = strtoupper($request->ciudad_municipio_proyecto);
        $proyecto->direccion_proyecto = strtoupper($request->direccion_proyecto);

        // $proyecto->fecha_inicio_proyecto = Carbon::createFromFormat("d/m/Y", $request->fecha_inicio_proyecto)->format("Y-m-d");
        // $proyecto->fecha_final_proyecto = Carbon::createFromFormat("d/m/Y", $request->fecha_final_proyecto)->format("Y-m-d");

        $proyecto->fecha_inicio_proyecto = $request->fecha_inicio_proyecto;
        $proyecto->fecha_final_proyecto = $request->fecha_inicio_proyecto;
        $proyecto->save();

        return ResponseHelper::success(200, "Se ha actualizado con exto", ["proyecto" => $proyecto]);
    }

    public function generateCSV($codigo_proyecto)
    {

        $proyecto = Proyecto::with(['inmuebles.presupuestos', "inmuebles.asignaciones"])->find($codigo_proyecto);

        if (!$proyecto) {


            return ResponseHelper::error(404, "El proyecto no existe");
        }

        // return response()->json(
        //     [
        //         "presupuesto" => count($proyecto->inmuebles[1]->presupuestos),
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
            "Progeso Total"
        ]);

        foreach ($proyecto->inmuebles->presupuestos as $presupuesto) {
            $archivoCSV->insertOne([
                $presupuesto["codigo_proyecto"],
                $presupuesto["nombre_inmueble"],
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

        return ResponseHelper::success(201, "El reporte se ha generado y guardado correctamente", ["proyecto" => $filePath]);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto|min:4",

        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        $proyecto = Proyecto::find($request->codigo_proyecto);

        $ValidarExistenciaPresupuesto = Presupuesto::where("codigo_proyecto", "=", $proyecto->codigo_proyecto)->exists();

        if (!$ValidarExistenciaPresupuesto) {
            return ResponseHelper::error(403, "Este proyecto no se puede eliminar porque tiene inmuebles con presupuesto");
        }

        if ($proyecto->estado === 'F') {
            return ResponseHelper::error(403, "Este proyecto no se puede eliminar porque ya esta finalizado");
        }

        $proyecto->update(["estado" => "E"]);

        return ResponseHelper::success(200, "Se ha eliminado con exito");
    }
}
