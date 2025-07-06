<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Cuota extends Model
{
    use HasFactory;

    protected $table = 'cuotas';
    protected $primaryKey = 'idCuota';
    
    // Especifica explícitamente la clave foránea
    protected $foreignKey = 'idPrestamo';
    
    protected $fillable = [
        'idPrestamo', 'numero_cuota', 'monto', 'capital', 'interes',
        'fecha_vencimiento', 'estado', 'dias_mora' ,'cargo_mora' , 'ajuste_tarde_aplicado' , 'mora_aplicada', 'mora_reducida', 'reduccion_mora_aplicada',
        'fecha_ajuste_tarde', 'fecha_mora_aplicada', 'observaciones'
    ];
    protected $casts = [
        'fecha_vencimiento' => 'datetime', // Treat as Carbon instance
    ];
    
     // Forzar formato date en el atributo
    public function getFechaVencimientoAttribute($value)
    {
        return Carbon::parse($value)->toDateString(); // Returns YYYY-MM-DD
    }

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'idPrestamo', 'idPrestamo');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'idCuota', 'idCuota');
    }
}