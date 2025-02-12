<?php

use App\Helpers\ResponseHelper;
use App\Http\Controllers\AsignacioneController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\MaterialeController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\TipoInmuebleController;
use App\Http\Controllers\UserController;

use Illuminate\Support\Facades\Route;

Route::post('auth/login', action: LoginController::class);


Route::middleware("auth:api")->group(function(){
    Route::prefix("auth")->group( function () {
        Route::post('/logout', action: LogoutController::class);
    });
    Route::prefix("usuario")->group(function () {
        Route::post("/", [UserController::class, 'store']);
        Route::get("/", [UserController::class, 'index']);
        Route::get("/{numero_identificacion}", [UserController::class, 'show']);
        Route::put("/changePassword", [UserController::class, 'changePassword']);
        Route::put("/changePasswordAdmin", [UserController::class, 'changePasswordAdmin']);
        Route::put("/{numero_identificacion}", [UserController::class, 'update']);
        Route::delete("/", [UserController::class, 'destroy']);
    });
    Route::prefix('materiale')->group(function () {
        Route::get("/", [MaterialeController::class, "index"]);
        Route::get("/{referencia_material}", [MaterialeController::class, "show"]);
        Route::post("/", [MaterialeController::class, "store"]);
        Route::put("/{referencia_material}", [MaterialeController::class, "update"]);
        Route::delete("/", [MaterialeController::class, 'destroy']);
    });
    
    Route::prefix("proyecto")->group(function () {
        Route::get("/", [ProyectoController::class, 'index']);
        Route::get("/select", [ProyectoController::class, 'select']);
        Route::get("/{codigo_proyecto}", [ProyectoController::class, 'show']);
        Route::get("/presupuesto/{codigo_proyecto}", [ProyectoController::class, 'showWithPresupuesto']);
        Route::post("/", [ProyectoController::class, 'store']);
        Route::put("/{codigo_proyecto}", [ProyectoController::class, 'update']);
        Route::delete("/", [ProyectoController::class, 'destroy']);
        
    });
    
    Route::prefix("tipo_inmueble")->group(function(){
        Route::get("/", [TipoInmuebleController::class,'index']);
        Route::get("/{id}", [TipoInmuebleController::class,'show']);
        Route::post("/", [TipoInmuebleController::class,'store']);
        Route::put("/{id}", [TipoInmuebleController::class,'update']);
        Route::delete("/", [TipoInmuebleController::class,'destroy']);
    });
    
    Route::prefix('inmueble')->group(function(){
        Route::post("/",[InmuebleController::class,"store"]);
        Route::get("/",[InmuebleController::class,"index"]);
        Route::get("/{id}",[InmuebleController::class,"show"]);
        Route::delete("/", [InmuebleController::class,'destroy']);
        Route::get("/report/{id}", [InmuebleController::class,"generateCSV"]);
    });
    
    Route::prefix('presupuesto')->group(function () {
        Route::post("/", [PresupuestoController::class, "store"]);
        Route::post("/file", [PresupuestoController::class, "fileMasivo"]);
        Route::get("/", [PresupuestoController::class, "show"]);
        Route::put("/", [PresupuestoController::class, "update"]);
        Route::get("/report/{codigo_proyecto}", [PresupuestoController::class, "generateCSV"]);
        Route::delete("/", [PresupuestoController::class, "destroy"]);
    });
    
    Route::prefix('asignacion')->group(function () {
        Route::post("/", [AsignacioneController::class, "store"]);
        Route::delete("/", [AsignacioneController::class, "destroy"]);

    });
    Route::prefix('inventario')->group(function () {
        Route::post("/", [InventarioController::class, "store"]);
        Route::get("/{referencia_material}/{consecutivo}",[InventarioController::class,"show"]);
        Route::put("/",[InventarioController::class,"update"]);
        
    });

    Route::fallback(function () {
        return ResponseHelper::error(404, "La ruta no existe");
    });
});





