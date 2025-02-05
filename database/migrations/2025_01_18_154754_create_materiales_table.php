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
        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            $table->string("referencia_material", 10);
            $table->string("nombre_material", 255);
            
            $table->char("estado",1)->default("A");

            $table->unique(["referencia_material","nombre_material"]);
            $table->string("numero_identificacion", 20);
            $table->foreign("numero_identificacion")->references("numero_identificacion")->on("usuarios");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materiales');
    }
};
