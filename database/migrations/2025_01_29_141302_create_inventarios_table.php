<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            // $table->id();
            $table->string("referencia_material");
            $table->bigInteger("consecutivo");
            
            $table->primary(["referencia_material","consecutivo"]);

            $table->decimal("costo");
            $table->string("cantidad");
            $table->string("nit_proveedor");
            $table->string("nombre_proveedor");
            $table->string("descripcion_proveedor")->nullable();
            $table->char("estado",1)->default("A");
            $table->string("numero_identificacion", 20);
            
            $table->foreign("numero_identificacion")->references("numero_identificacion")->on("usuarios");
            $table->foreign("referencia_material")->references("referencia_material")->on("materiales");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
