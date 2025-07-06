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
        Schema::create('cliente_avales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idCliente');
            $table->unsignedBigInteger('idAval');
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('idCliente')->references('idUsuario')->on('usuarios')->onDelete('cascade');
            $table->foreign('idAval')->references('idUsuario')->on('usuarios')->onDelete('cascade');
            
            // Ensure a client can only have one guarantor
            $table->unique('idCliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_avales');
    }
};