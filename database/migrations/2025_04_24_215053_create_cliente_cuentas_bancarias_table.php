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
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->bigIncrements('idCuenta');
            $table->unsignedBigInteger('idDatos');
            $table->string('numeroCuenta')->unique();
            $table->string('cci')->nullable()->unique();
            $table->string('entidadFinanciera');
            $table->timestamps();
        
            $table->foreign('idDatos')->references('idDatos')->on('datos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_cuentas_bancarias');
    }
};
