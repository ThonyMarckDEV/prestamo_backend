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
        Schema::create('contactos', function (Blueprint $table) {
            $table->bigIncrements('idContacto');
            $table->unsignedBigInteger('idDatos');
            $table->enum('tipo', ['PRINCIPAL', 'SECUNDARIO']);
            $table->string('telefono')->unique();
            $table->string('telefonoDos')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamps();
        
            $table->foreign('idDatos')->references('idDatos')->on('datos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_contactos');
    }
};
