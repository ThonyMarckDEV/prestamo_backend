<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoPrestamo extends Model
{
    use HasFactory;

    protected $table = 'estados_prestamo';

    protected $primaryKey = 'idEstadoPrestamo';
    
    protected $fillable = [
        'idPrestamo', 'estado', 'veces_reprogramado', 'veces_refinanciado' , 'fecha_actualizacion',
        'observacion', 'idUsuario'
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'idPrestamo', 'idPrestamo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }
}