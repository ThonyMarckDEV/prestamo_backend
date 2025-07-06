<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id('idToken');
            $table->unsignedBigInteger('idUsuario');
            $table->string('token')->unique();
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};