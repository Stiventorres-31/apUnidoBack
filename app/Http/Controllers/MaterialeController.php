<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Asignacione;
use App\Models\Inventario;
use App\Models\Materiale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class MaterialeController extends Controller
{

    public function index()
    {
        $materiale = Materiale::where("estado","=","A")->get();


        return ResponseHelper::success(200,"Todos los materiales registrados",["materiale"=>$materiale]);
    }

    public function show($referencia_material)
    {
        $validator = Validator::make(["referencia_material" => $referencia_material], [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);
        if ($validator->fails()) {
         

            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }


        $materiale = Materiale::with("inventario")
            ->where("referencia_material", "=", $referencia_material)
            ->where("estado", "=", "A")
            ->first();

        if (!$materiale) {
            
            return ResponseHelper::error(404,"No se ha encontrado material",[]);
        }


        return ResponseHelper::success(200,"Se ha encontrado el material",["material" => $materiale]);
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
            "descripcion_proveedor" => "required|min:6",
        ]);



        if ($validatorData->fails()) {
          

            return ResponseHelper::error(422,$validatorData->errors()->first(),$validatorData->errors());
        }

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


        return ResponseHelper::success(201,"Material creado exitosamente",['materiale' => $materiale]);
    }

    public function update(Request $request, $referencia_material)
    {

        $validatedata = Validator::make($request->all(), [
            "costo" => "required|regex:/^\d{1,10}(\.\d{1,2})?$/",
            "cantidad" => "required|numeric|min:0"
        ]);

        // Si la validación falla, devolver una respuesta de error 422
        if ($validatedata->fails()) {
          

            return ResponseHelper::error(422,$validatedata->errors()->first(),$validatedata->errors());
        }

        // Buscar el material por referencia
        $materiale = Materiale::where('referencia_material', trim($referencia_material))->first();

        // Verificar si el material existe
        if (!$materiale) {
         
            return ResponseHelper::error(404,"Material no encontrado");
        }

        // Actualizar los datos del material
        $materiale->update([
            'costo' => trim($request->costo),
            'cantidad' => trim($request->cantidad),
        ]);

        return ResponseHelper::success(200,"Material actualizado exitosamente", ['materiale' => $materiale]);
    }

    public function destroy(Request $request)
    {


        $validateData = Validator::make($request->all(), [
            "referencia_material" => "required|exists:materiales,referencia_material"
        ]);

        if ($validateData->fails()) {
        
            return ResponseHelper::error(422,$validateData->errors()->first(),$validateData->errors());
        }


        $asignacion = Asignacione::where("referencia_material", "=", $request->referencia_material)->first();

        if ($asignacion) {
            
            return ResponseHelper::error(400,"No se puede eliminar este material");
        }

        $materiale = Materiale::where("referencia_material", "=", $request->referencia_material)->first();


        // Actualizar el estado a "E" (Eliminado o Inactivo)
        $materiale->estado = "E";
        $materiale->save();

      
        return ResponseHelper::success(200,"Se ha eliminado el material correctamente");
    }

    public function storeInventario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "referencia_material" => "required|string|exists:materiales,referencia_material",
            "costo" => "required|numeric",
            "cantidad" => "required|numeric",
            "nit_proveedor" => "required",
            "nombre_proveedor" => "required",
        ]);

        if ($validator->fails()) {
            

            return ResponseHelper::error(422,$validator->errors()->first(),$validator->errors());
        }

        if (!is_string($request->referencia_material)) {
          

            return ResponseHelper::error(422,"El campo referencia_material no es válido");

        }
        $consecutivo = Inventario::where("referencia_material", "=", $request->referencia_material)
            ->max("consecutivo") ?? 0;

        $inventario =  new Inventario();
        $inventario->referencia_material = strtoupper(trim($request->referencia_material));
        // return response()->json($request->referencia_material);
        $inventario->consecutivo = $consecutivo + 1;
        $inventario->costo = (float) trim($request->costo);
        $inventario->cantidad = trim($request->cantidad);
        $inventario->nit_proveedor = trim($request->nit_proveedor);
        $inventario->nombre_proveedor = strtoupper(trim($request->nombre_proveedor));
        $inventario->descripcion_proveedor = "Fer";
        $inventario->numero_identificacion = Auth::user()->numero_identificacion;
        $inventario->save();


        return ResponseHelper::success(200,"Se ha registrado con exito",["inventario" => $inventario]);

    }
}
