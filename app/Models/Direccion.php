<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    use HasFactory;

    protected $table = 'direcciones';
    protected $primaryKey = 'idDireccion';

    protected $fillable = [
        'idDatos',
        'tipo',
        'tipoVia',
        'nombreVia',
        'numeroMz',
        'urbanizacion',
        'departamento',
        'provincia',
        'distrito'
    ];

    public function datos()
    {
        return $this->belongsTo(Datos::class, 'idDatos');
    }
}