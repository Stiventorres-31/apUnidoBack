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
        Schema::create('inmuebles', function (Blueprint $table) {
         
            $table->id();
            $table->string("nombre_inmueble")->unique();
            $table->string("estado",1)->default("A");
            $table->string("codigo_proyecto", 10);
            $table->string("numero_identificacion", 20);
            
            $table->foreignId("tipo_inmueble")->references("id")->on("tipo_inmuebles");
            $table->foreign("codigo_proyecto")->references("codigo_proyecto")->on("proyectos");
            $table->foreign("numero_identificacion")->references("numero_identificacion")->on("usuarios");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inmuebles');
    }
};
