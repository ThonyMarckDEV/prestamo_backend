<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadEconomica extends Model
{
    use HasFactory;

    protected $table = 'actividades_economicas';
    protected $primaryKey = 'idActividad';

    protected $fillable = [
        'idDatos',
        'idCiiu',
        'idNoSensible'
    ];

    public function datos()
    {
        return $this->belongsTo(Datos::class, 'idDatos');
    }

    public function ciiu()
    {
        return $this->belongsTo(Ciiu::class, 'idCiiu');
    }

    public function noSensible()
    {
        return $this->belongsTo(NoSensible::class, 'idNoSensible');
    }
}