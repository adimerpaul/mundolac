<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';
    protected $fillable = ['nombre', 'nit', 'telefono', 'celular', 'email', 'direccion', 'zona', 'latitud', 'longitud', 'foto', 'referencia', 'observacion', 'activo'];
    protected $casts = ['latitud' => 'decimal:7', 'longitud' => 'decimal:7', 'activo' => 'boolean'];
    public function pedidos() { return $this->hasMany(Pedido::class); }
}
