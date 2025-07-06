<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * La clave primaria asociada con la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'idRol';

    /**
     * Los atributos que se pueden asignar de manera masiva.
     *
     * @var array<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /**
     * Relación con los usuarios
     */
    public function usuarios()
    {
        return $this->hasMany(User::class, 'idRol', 'idRol');
    }
}