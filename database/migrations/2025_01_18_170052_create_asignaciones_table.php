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
        Schema::create('asignaciones', function (Blueprint $table) {
           
            $table->string("numero_identificacion", 20);
            $table->string("referencia_material",10);
            $table->string("nombre_inmueble");
            $table->string("codigo_proyecto");

            $table->float("cantidad_material");
            $table->decimal("costo_material");

            $table->primary(["referencia_material", "nombre_inmueble", "codigo_proyecto"]);
           
            $table->char("estado",1)->default("A");
            $table->foreign('numero_identificacion')->references("numero_identificacion")->on("usuarios");
            $table->foreign('referencia_material')->references("referencia_material")->on("materiales");
            $table->foreign("nombre_inmueble")->references("nombre_inmueble")->on("inmuebles");
            $table->foreign("codigo_proyecto")->references("codigo_proyecto")->on("proyectos");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
