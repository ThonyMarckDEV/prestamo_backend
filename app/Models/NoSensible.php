<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoSensible extends Model
{
    protected $table = 'no_sensibles';
    protected $primaryKey = 'idNoSensible';
    protected $fillable = ['codigo', 'descripcion'];

    public function actividadesEconomicas()
    {
        return $this->hasMany(ActividadEconomica::class, 'idNoSensible');
    }
}