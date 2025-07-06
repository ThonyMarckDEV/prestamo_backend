<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->bigIncrements('idUsuario');
            $table->string('username')->unique()->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('idDatos')->nullable();
            $table->unsignedBigInteger('idRol')->default(2); // Por defecto 2 que es cliente
            $table->boolean('estado')->default(1)->comment('1: Activo, 0: Inactivo');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('idDatos')->references('idDatos')->on('datos')->onDelete('cascade');
            $table->foreign('idRol')->references('idRol')->on('roles')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};