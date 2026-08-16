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
        'numero', 'user_id', 'usuario_nombre', 'subtotal', 'descuento',
        'total', 'tipo_pago', 'monto_efectivo', 'monto_qr',
        'estado', 'observacion', 'fecha',
        'tipo_pago_original', 'pago_cambiado', 'pago_cambiado_en',
        'pago_cambiado_user_id', 'pago_cambiado_por', 'pago_cambiado_motivo',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2',
        'total' => 'decimal:2', 'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2', 'fecha' => 'datetime',
        'pago_cambiado' => 'boolean', 'pago_cambiado_en' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
