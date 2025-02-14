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

            $table->id();
            $table->unsignedBigInteger("inmueble_id");

            $table->string("referencia_material",10);
            $table->string("numero_identificacion",20);
            $table->unsignedInteger("consecutivo");
            
            $table->decimal("costo_material");
            $table->unsignedBigInteger("subtotal");
            $table->decimal("cantidad_material");
            $table->string("codigo_proyecto",10);

            // $table->char("estado",1)->default("A");
            //$table->enum("estado", ["A", "I"])->default("A");

            $table->unique(["inmueble_id","codigo_proyecto","referencia_material","consecutivo"],"llave_unida");

            $table->foreign("inmueble_id")->references("id")->on("inmuebles");
            $table->foreign("referencia_material")->references("referencia_material")->on("materiales");
            $table->foreign("codigo_proyecto")->references("codigo_proyecto")->on("proyectos");
            $table->foreign("numero_identificacion")->references("numero_identificacion")->on( "usuarios");
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
