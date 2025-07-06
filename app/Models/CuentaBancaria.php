<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaBancaria extends Model
{
    use HasFactory;

    protected $table = 'cuentas_bancarias';
    protected $primaryKey = 'idCuenta';

    protected $fillable = [
        'idDatos',
        'numeroCuenta',
        'cci',
        'entidadFinanciera',
    ];

    public function datos()
    {
        return $this->belongsTo(Datos::class, 'idDatos');
    }
}