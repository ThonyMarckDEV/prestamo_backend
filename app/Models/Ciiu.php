<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciiu extends Model
{
    protected $table = 'ciiu';
    protected $primaryKey = 'idCiiu';
    protected $fillable = ['codigo', 'descripcion'];

    public function actividadesEconomicas()
    {
        return $this->hasMany(ActividadEconomica::class, 'idCiiu');
    }
}

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