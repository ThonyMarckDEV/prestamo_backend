<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla para actividades CIE
        Schema::create('ciiu', function (Blueprint $table) {
            $table->bigIncrements('idCiiu');
            $table->string('codigo');
            $table->string('descripcion');
            $table->timestamps();
        });

        // Tabla para actividades NO_SENSIBLES
        Schema::create('no_sensibles', function (Blueprint $table) {
            $table->bigIncrements('idNoSensible');
            $table->string('sector');
            $table->string('actividad');
            $table->string('margen_maximo');
            $table->timestamps();
        });

        // Tabla pivote para relacionar usuarios con actividades económicas
        Schema::create('actividades_economicas', function (Blueprint $table) {
            $table->bigIncrements('idActividad');
            $table->unsignedBigInteger('idDatos');
            $table->unsignedBigInteger('idCiiu')->nullable();
            $table->unsignedBigInteger('idNoSensible')->nullable();
            $table->timestamps();
            
            $table->foreign('idDatos')->references('idDatos')->on('datos')->onDelete('cascade');
            $table->foreign('idCiiu')->references('idCiiu')->on('ciiu')->onDelete('set null');
            $table->foreign('idNoSensible')->references('idNoSensible')->on('no_sensibles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades_economicas');
        Schema::dropIfExists('no_sensibles');
        Schema::dropIfExists('ciiu');
    }
};