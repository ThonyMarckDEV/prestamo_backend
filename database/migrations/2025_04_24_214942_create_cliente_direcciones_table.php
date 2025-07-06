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
        Schema::create('direcciones', function (Blueprint $table) {
            $table->bigIncrements('idDireccion');
            $table->unsignedBigInteger('idDatos');
            $table->enum('tipo', ['FISCAL', 'CORRESPONDENCIA'])->nullable();
            $table->string('tipoVia')->nullable();
            $table->string('nombreVia')->nullable();
            $table->string('numeroMz')->nullable();
            $table->string('urbanizacion');
            $table->string('departamento');
            $table->string('provincia');
            $table->string('distrito');
            $table->timestamps();
        
            $table->foreign('idDatos')->references('idDatos')->on('datos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
