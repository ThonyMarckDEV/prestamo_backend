<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{

    protected $primaryKey = 'idProducto';

    protected $table = 'productos';
    protected $fillable = ['nombre', 'rango_tasa'];
}
?>