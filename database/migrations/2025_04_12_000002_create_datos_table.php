<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datos', function (Blueprint $table) {
            $table->bigIncrements('idDatos');
            $table->string('nombre');
            $table->string('apellidoPaterno');
            $table->string('apellidoMaterno');
            $table->string('apellidoConyuge')->nullable();
            $table->string('estadoCivil');
            $table->string('dni', 9)->unique();
            $table->date('fechaCaducidadDni');
            $table->string('ruc', 11)->nullable()->unique();
            $table->boolean('expuesta')->default(false);
            $table->boolean('aval')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos');
    }
};