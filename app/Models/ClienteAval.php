<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteAval extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'cliente_avales';

    /**
     * Los atributos que se pueden asignar de manera masiva.
     *
     * @var array<string>
     */
    protected $fillable = [
        'idCliente',
        'idAval',
    ];

    /**
     * Relación con el usuario cliente
     */
    public function cliente()
    {
        return $this->belongsTo(User::class, 'idCliente', 'idUsuario');
    }

    /**
     * Relación con el usuario aval
     */
    public function aval()
    {
        return $this->belongsTo(User::class, 'idAval', 'idUsuario');
    }
}