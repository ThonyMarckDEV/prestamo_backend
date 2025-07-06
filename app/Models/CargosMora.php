<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargosMora extends Model
{
    protected $table = 'cargos_mora';
    protected $fillable = [
        'dias',
        'monto_300_900',
        'monto_1000_1500',
        'monto_1600_2000',
        'monto_2100_2500',
        'monto_2501_3000',
        'monto_3001_3500',
        'monto_3501_4000',
        'monto_4001_4500',
        'monto_4501_5000',
        'monto_5001_5500',
        'monto_5501_6000',
    ];
}