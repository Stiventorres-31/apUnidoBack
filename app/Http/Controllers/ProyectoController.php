<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\Writer;
use Throwable;

class ProyectoController extends Controller
{
    public function index()
    {



        try {
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
                ->where("estado", "=", "A")->paginate(2);
            return ResponseHelper::success(200, "Listado de proyectos", ["proyectos" => $proyectos]);
        } catch (Throwable $th) {
            Log::error("error al obtener todos los proyectos " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function select()
    {
        try {
            $proyecto = Proyecto::select("id", "codigo_proyecto")->where("estado", "A")->get();
            return ResponseHelper::success(200, "Proyectos", $proyecto);
        } catch (Throwable $th) {
            Log::error("error en el select del proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
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

        try {
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
        } catch (Throwable $th) {
            Log::error("error al registrar un proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
    public function showWithPresupuesto($codigo_proyecto)
    {

        try {
            $proyecto = Proyecto::with(['inmuebles.presupuestos', 'inmuebles.tipo_inmueble'])->find($codigo_proyecto);

            if (!$proyecto) {
                return ResponseHelper::error(404, "Proyecto no encontrado");
            }
            $proyectoArray = $proyecto->toArray();


            foreach ($proyectoArray['inmuebles'] as &$inmueble) {
                $inmueble['total_presupuesto'] = collect($inmueble['presupuestos'])->sum('subtotal');
                unset($inmueble['presupuestos']);
            }

            return ResponseHelper::success(200, "Proyecto obtenido", ["proyecto" => $proyectoArray]);
        } catch (Throwable $th) {
            Log::error("error al consultar un proyecto con el presupuesto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }




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
        try {

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
        } catch (Throwable $th) {
            Log::error("error al consultar un proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function update(Request $request, $codigo_proyecto)
    {
        $validator = Validator::make(["codigo_proyecto"=>$codigo_proyecto], [
            "codigo_proyecto" => "required|regex:/^[A-Za-z0-9\-]+$/"
        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

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

            "fecha_inicio_proyecto" => "sometimes|date_format:Y-m-d",
            "fecha_final_proyecto" => "sometimes|date_format:Y-m-d|after:fecha_inicio_proyecto"

        ]);

        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }
        try {

            $proyecto->departamento_proyecto = strtoupper($request->departamento_proyecto);
            $proyecto->ciudad_municipio_proyecto = strtoupper($request->ciudad_municipio_proyecto);
            $proyecto->direccion_proyecto = strtoupper($request->direccion_proyecto);

            // $proyecto->fecha_inicio_proyecto = Carbon::createFromFormat("d/m/Y", $request->fecha_inicio_proyecto)->format("Y-m-d");
            // $proyecto->fecha_final_proyecto = Carbon::createFromFormat("d/m/Y", $request->fecha_final_proyecto)->format("Y-m-d");

            $proyecto->fecha_inicio_proyecto = $request->fecha_inicio_proyecto;
            $proyecto->fecha_final_proyecto = $request->fecha_inicio_proyecto;
            $proyecto->save();

            return ResponseHelper::success(200, "Se ha actualizado con exto", ["proyecto" => $proyecto]);
        } catch (Throwable $th) {
            Log::error("error al actualizar la informacion de un proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }



    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "codigo_proyecto" => "required|exists:proyectos,codigo_proyecto|min:4",

        ]);
        if ($validator->fails()) {
            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }

        try {
            $proyecto = Proyecto::find($request->codigo_proyecto);

            $ValidarExistenciaPresupuesto = Presupuesto::where("codigo_proyecto", "=", $proyecto->codigo_proyecto)->exists();

            if ($ValidarExistenciaPresupuesto) {
                return ResponseHelper::error(401, "Este proyecto no se puede eliminar porque tiene inmuebles con presupuesto");
            }

            if ($proyecto->estado === 'F') {
                return ResponseHelper::error(401, "Este proyecto no se puede eliminar porque ya esta finalizado");
            }

            $proyecto->update(["estado" => "E"]);

            return ResponseHelper::success(200, "Se ha eliminado con exito");
        } catch (Throwable $th) {
            Log::error("error al eliminar un proyecto " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
