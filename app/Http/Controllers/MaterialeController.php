<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inventario;
use App\Models\Materiale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\TryCatch;

class MaterialeController extends Controller
{

    public function index()
    {
        try {
            $materiale = Materiale::where("estado", "=", "A")->get();
            return ResponseHelper::success(200, "Todos los materiales registrados", ["materiale" => $materiale]);
        } catch (\Throwable $th) {
            Log::error("error al mostrar todos los materiales activos " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function show($referencia_material)
    {
        $validator = Validator::make(["referencia_material" => $referencia_material], [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);
        if ($validator->fails()) {


            return ResponseHelper::error(422, $validator->errors()->first(), $validator->errors());
        }


        try {
            $materiale = Materiale::with("inventario")
                ->where("referencia_material", "=", $referencia_material)
                ->where("estado", "=", "A")
                ->first();

            if (!$materiale) {

                return ResponseHelper::error(404, "No se ha encontrado material", []);
            }
        } catch (\Throwable $th) {
            Log::error("error al consultar 1 material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }



        return ResponseHelper::success(200, "Se ha encontrado el material", ["material" => $materiale]);
    }
    public function store(Request $request)
    {



        $validatorData = Validator::make($request->all(), [
            "referencia_material" => "required|unique:materiales|max:10",
            "nombre_material" => "required|unique:materiales|min:6",
            "costo" => "required|regex:/^\d{1,10}(\.\d{1,2})?$/",
            "cantidad" => "required|numeric|min:0",
            "nit_proveedor" => "required|min:6",
            "nombre_proveedor" => "required|min:6",
            "descripcion_proveedor" => "nullable|min:6",
        ]);



        if ($validatorData->fails()) {


            return ResponseHelper::error(422, $validatorData->errors()->first(), $validatorData->errors());
        }

        try {
            $materiale = new Materiale();

            $materiale->referencia_material = strtoupper(trim($request->referencia_material));
            $materiale->nombre_material = strtoupper(trim($request->nombre_material));
            $materiale->numero_identificacion = Auth::user()->numero_identificacion;
            $materiale->save();

            $inventario = new Inventario();

            $inventario->referencia_material = strtoupper(trim($materiale->referencia_material));
            $inventario->consecutivo = 1;
            $inventario->numero_identificacion = Auth::user()->numero_identificacion;
            $inventario->costo = trim($request->costo);
            $inventario->cantidad = trim($request->cantidad);
            $inventario->nit_proveedor = trim($request->nit_proveedor);
            $inventario->nombre_proveedor = strtoupper(trim($request->nombre_proveedor));
            $inventario->descripcion_proveedor = strtoupper(trim($request->descripcion_proveedor));

            $inventario->save();


            return ResponseHelper::success(201, "Material creado exitosamente", ['materiale' => $materiale]);
        } catch (\Throwable $th) {
            Log::error("error al crear un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function update(Request $request)
    {

        $validatedata = Validator::make($request->all(), [
            "nombre_material" => "required",
            "id" => "required|exists:materiales,id"
        ]);


        if ($validatedata->fails()) {
            return ResponseHelper::error(422, $validatedata->errors()->first(), $validatedata->errors());
        }


        try {
            $materiale = Materiale::find($request->id);


            if (!$materiale) {
                return ResponseHelper::error(404, "Material no encontrado");
            }
            if ($materiale->nombre_material === trim(strtoupper($request->nombre_material))) {
                return ResponseHelper::error(422, "Este nombre ya esta registrado");
            }

            $materiale->update([
                'nombre_material' => trim($request->nombre_material)
            ]);

            return ResponseHelper::success(200, "Material actualizado exitosamente", ['materiale' => $materiale]);
        } catch (\Throwable $th) {
            Log::error("error al actualizar el nombre del material " . $th->getMessage());

            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }

    public function destroy(Request $request)
    {


        $validateData = Validator::make($request->all(), [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);

        if ($validateData->fails()) {

            return ResponseHelper::error(422, $validateData->errors()->first(), $validateData->errors());
        }


        try {
            $asignacion = Asignacione::where("referencia_material", "=", $request->referencia_material)->first();

            if ($asignacion) {

                return ResponseHelper::error(400, "No se puede eliminar este material");
            }

            $materiale = Materiale::where("referencia_material", "=", $request->referencia_material)->first();


            // Actualizar el estado a "E" (Eliminado o Inactivo)
            $materiale->estado = "E";
            $materiale->save();


            return ResponseHelper::success(200, "Se ha eliminado el material correctamente");
        } catch (\Throwable $th) {
            Log::error("error al eliminar un material " . $th->getMessage());
            return ResponseHelper::error(500, "Error interno en el servidor");
        }
    }
}
