<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $table = 'prestamos';

    protected $primaryKey = 'idPrestamo';
    
    protected $fillable = [
        'idCliente', 'idGrupo', 'monto', 'interes', 'total', 'cuotas',
        'valor_cuota', 'frecuencia', 'modalidad', 'fecha_generacion', 
        'fecha_inicio', 'idAsesor', 'estado', 'idProducto', 'abonado_por'
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'idCliente', 'idUsuario');
    }

    public function asesor()
    {
        return $this->belongsTo(User::class, 'idAsesor', 'idUsuario');
    }

    public function cuotas()
    {
        return $this->hasMany(Cuota::class, 'idPrestamo', 'idPrestamo');
    }

    public function estados()
    {
        return $this->hasMany(EstadoPrestamo::class, 'idPrestamo', 'idPrestamo');
    }

    public function estadoPrestamo()
    {
        return $this->hasMany(EstadoPrestamo::class, 'idPrestamo', 'idPrestamo');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'idGrupo', 'idGrupo');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }
}
?>