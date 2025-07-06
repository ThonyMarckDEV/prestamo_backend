<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens'; // Nombre de la tabla personalizada

    protected $primaryKey = 'idToken'; // Clave primaria personalizada

    protected $fillable = [
        'idUsuario',
        'refresh_token',
        'expires_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }
}
