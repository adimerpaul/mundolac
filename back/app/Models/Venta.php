<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Venta extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'tipo', 'user_id', 'pedido_id', 'venta_origen_id', 'usuario_nombre', 'subtotal', 'descuento',
        'total', 'monto_pagado', 'tipo_pago', 'monto_efectivo', 'monto_qr',
        'estado', 'observacion', 'fecha', 'fecha_pago',
        'tipo_pago_original', 'pago_cambiado', 'pago_cambiado_en',
        'pago_cambiado_user_id', 'pago_cambiado_por', 'pago_cambiado_motivo',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2',
        'total' => 'decimal:2', 'monto_pagado' => 'decimal:2', 'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2', 'fecha' => 'datetime', 'fecha_pago' => 'datetime',
        'pago_cambiado' => 'boolean', 'pago_cambiado_en' => 'datetime',
    ];

    protected $appends = ['saldo'];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    /** INGRESO de cobro → venta a crédito que salda. */
    public function origen()
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
    }

    /** Pagos recibidos (al contado y cobros posteriores). */
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    /** Lo que falta cobrar. */
    public function getSaldoAttribute(): float
    {
        return round((float) $this->total - (float) $this->monto_pagado, 2);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
