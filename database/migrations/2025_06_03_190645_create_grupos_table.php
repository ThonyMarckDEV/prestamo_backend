<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create grupos table
        Schema::create('grupos', function (Blueprint $table) {
            $table->bigIncrements('idGrupo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('idAsesor');
            $table->date('fecha_creacion');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
            
            $table->foreign('idAsesor')->references('idUsuario')->on('usuarios');
        });

        // Add idGrupo to prestamos table
        Schema::table('prestamos', function (Blueprint $table) {
            $table->unsignedBigInteger('idGrupo')->nullable()->after('idCliente');
            $table->foreign('idGrupo')->references('idGrupo')->on('grupos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropForeign(['idGrupo']);
            $table->dropColumn('idGrupo');
        });
        
        Schema::dropIfExists('grupos');
    }
};