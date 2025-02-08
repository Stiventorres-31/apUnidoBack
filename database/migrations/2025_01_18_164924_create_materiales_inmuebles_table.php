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
        Schema::create('presupuestos', function (Blueprint $table) {
           
            $table->unsignedBigInteger("inmueble_id");
            $table->string("referencia_material",10);
            $table->string("numero_identificacion",20);
            
            $table->decimal("costo_material",10,2);
            $table->decimal("subtotal",10,2);
            $table->float("cantidad_material");
            $table->string("codigo_proyecto");

            // $table->unsignedBigInteger("consecutivo",false);
            // $table->char("estado",1)->default("A");

            $table->primary(["inmueble_id","codigo_proyecto","referencia_material"]);

            $table->foreign("inmueble_id")->references("id")->on("inmuebles");
            $table->foreign("referencia_material")->references("referencia_material")->on("materiales");
            $table->foreign("codigo_proyecto")->references("codigo_proyecto")->on("proyectos");
            $table->foreign("numero_identificacion")->references("numero_identificacion")->on(table: "usuarios");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materiales_inmuebles');
    }
};
