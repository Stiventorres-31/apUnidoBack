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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('numero_identificacion', 20)->unique();
            $table->string('nombre_completo', 30);
            $table->string('password');
            $table->string('rol_usuario', 20);
            $table->char("estado",1)->default("A");
            $table->index(["numero_identificacion","nombre_completo"]);
            $table->unique(["numero_identificacion","nombre_completo"]);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
