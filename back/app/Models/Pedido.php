<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use SoftDeletes;
    protected $fillable = ['numero', 'cliente_id', 'cliente_nombre', 'user_id', 'usuario_nombre', 'fecha_entrega', 'direccion_entrega', 'latitud_entrega', 'longitud_entrega', 'tipo_pago', 'observacion', 'total', 'estado', 'fecha'];
    protected $casts = ['fecha' => 'datetime', 'fecha_entrega' => 'date', 'total' => 'decimal:2', 'latitud_entrega' => 'decimal:7', 'longitud_entrega' => 'decimal:7'];
    public function detalles() { return $this->hasMany(PedidoDetalle::class); }
    public function cliente() { return $this->belongsTo(Cliente::class); }
}
