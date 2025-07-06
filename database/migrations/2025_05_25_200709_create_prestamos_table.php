<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prestamos table
        Schema::create('prestamos', function (Blueprint $table) {
            $table->bigIncrements('idPrestamo');
            $table->unsignedBigInteger('idCliente');
            $table->decimal('monto', 10, 2);
            $table->decimal('interes', 5, 2);
            $table->decimal('total', 10, 2);
            $table->integer('cuotas');
            $table->decimal('valor_cuota', 10, 2);
            $table->enum('frecuencia', ['semanal', 'catorcenal', 'mensual']);
            $table->enum('modalidad', ['nuevo', 'RCS', 'RSS']);
            $table->date('fecha_generacion');
            $table->date('fecha_inicio');
            $table->unsignedBigInteger('idAsesor');
            $table->unsignedBigInteger('idProducto')->nullable();
            $table->enum('abonado_por', ['CUENTA CORRIENTE', 'CAJA CHICA'])->nullable();
            
            $table->enum('estado', ['activo', 'cancelado'])->default('activo');
            $table->timestamps();

            $table->foreign('idProducto')->references('idProducto')->on('productos')->onDelete('set null');
            $table->foreign('idCliente')->references('idUsuario')->on('usuarios');
            $table->foreign('idAsesor')->references('idUsuario')->on('usuarios');
        });

        // Cuotas table
        Schema::create('cuotas', function (Blueprint $table) {
            $table->bigIncrements('idCuota');
            $table->unsignedBigInteger('idPrestamo');
            $table->integer('numero_cuota');
            $table->decimal('monto', 10, 2);
            $table->decimal('capital', 10, 2);
            $table->decimal('interes', 10, 2);
            $table->date('fecha_vencimiento');
            $table->enum('estado', ['pendiente', 'pagado', 'vence_hoy', 'vencido', 'prepagado']);
            $table->integer('dias_mora')->default(0);
            $table->decimal('cargo_mora', 10, 2)->default(0.00);
            $table->boolean('ajuste_tarde_aplicado')->default(false);
            $table->boolean('mora_aplicada')->default(false);
            $table->decimal('mora_reducida', 5, 2)->default(0.00); // Percentage (0-100%)
            $table->boolean('reduccion_mora_aplicada')->default(false);
            $table->dateTime('fecha_ajuste_tarde')->nullable();
            $table->dateTime('fecha_mora_aplicada')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            $table->foreign('idPrestamo')->references('idPrestamo')->on('prestamos')->onDelete('cascade');
        });

        // Pagos table
        Schema::create('pagos', function (Blueprint $table) {
            $table->bigIncrements('idPago');
            $table->string('numero_operacion')->nullable();
            $table->unsignedBigInteger('idCuota');
            $table->decimal('monto_pagado', 10, 2);
            $table->decimal('excedente', 10, 2)->default(0);
            $table->date('fecha_pago');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('idUsuario');
            $table->enum('modalidad', ['presencial', 'virtual'])->default('presencial');
            $table->timestamps();
            
            $table->foreign('idCuota')->references('idCuota')->on('cuotas')->onDelete('cascade');
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios');
        });

        // Estados_prestamo table
        Schema::create('estados_prestamo', function (Blueprint $table) {
            $table->bigIncrements('idEstadoPrestamo');
            $table->unsignedBigInteger('idPrestamo');
            $table->enum('estado', ['vigente', 'cancelado', 'refinanciado', 'mora', 'reprogramado']);
            $table->integer('veces_reprogramado')->default(0);
            $table->integer('veces_refinanciado')->default(0);
            $table->date('fecha_actualizacion');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('idUsuario');
            $table->timestamps();
            
            $table->foreign('idPrestamo')->references('idPrestamo')->on('prestamos')->onDelete('cascade');
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios');
        });

    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('estados_prestamo');
        Schema::dropIfExists('prestamos');
    }
};