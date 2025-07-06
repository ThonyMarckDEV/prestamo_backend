<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCargosMoraTable extends Migration
{
    public function up()
    {
        Schema::create('cargos_mora', function (Blueprint $table) {
            $table->id(); // Opcional: Agrega un ID primario
            $table->string('dias', 50);
            $table->decimal('monto_300_900', 10, 2);
            $table->decimal('monto_1000_1500', 10, 2);
            $table->decimal('monto_1600_2000', 10, 2);
            $table->decimal('monto_2100_2500', 10, 2);
            $table->decimal('monto_2501_3000', 10, 2);
            $table->decimal('monto_3001_3500', 10, 2);
            $table->decimal('monto_3501_4000', 10, 2);
            $table->decimal('monto_4001_4500', 10, 2);
            $table->decimal('monto_4501_5000', 10, 2);
            $table->decimal('monto_5001_5500', 10, 2);
            $table->decimal('monto_5501_6000', 10, 2);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cargos_mora');
    }
}