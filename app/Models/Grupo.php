<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupos';
    protected $primaryKey = 'idGrupo';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'idAsesor',
        'fecha_creacion',
        'estado'
    ];

    public function asesor()
    {
        return $this->belongsTo(User::class, 'idAsesor', 'idUsuario');
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class, 'idGrupo', 'idGrupo');
    }
}