<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Pago extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'venta_id', 'pedido_id', 'user_id', 'usuario_nombre', 'tipo', 'monto', 'tipo_pago',
        'monto_efectivo', 'monto_qr', 'estado', 'observacion', 'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2', 'monto_efectivo' => 'decimal:2', 'monto_qr' => 'decimal:2', 'fecha' => 'datetime',
    ];

    protected $appends = ['numero'];

    /** Número correlativo derivado del id: PG-00000001. */
    public function getNumeroAttribute(): ?string
    {
        return $this->id ? 'PG-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT) : null;
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
