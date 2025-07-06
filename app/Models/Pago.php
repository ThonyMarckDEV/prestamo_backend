<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $primaryKey = 'idPago';
    
    protected $fillable = [
        'idCuota','numero_operacion', 'monto_pagado', 'excedente', 'fecha_pago',
        'observaciones', 'idUsuario' , 'modalidad'
    ];

    public function cuota()
    {
        return $this->belongsTo(Cuota::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }
}