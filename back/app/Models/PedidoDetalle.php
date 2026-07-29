<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoDetalle extends Model
{
    protected $fillable = ['pedido_id', 'producto_id', 'codigo', 'nombre', 'unidad', 'cantidad', 'precio_unitario', 'total'];
    protected $casts = ['cantidad' => 'decimal:3', 'precio_unitario' => 'decimal:4', 'total' => 'decimal:2'];
}
