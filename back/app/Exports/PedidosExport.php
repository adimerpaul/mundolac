<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PedidosExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $pedidos) {}
    public function collection(): Collection
    {
        return $this->pedidos->map(fn ($p) => [
            $p->numero, $p->fecha->format('d/m/Y H:i'), $p->cliente_nombre,
            $p->fecha_entrega?->format('d/m/Y'), $p->direccion_entrega,
            $p->tipo_pago, $p->detalles_count, $p->total, $p->estado, $p->usuario_nombre,
        ]);
    }
    public function headings(): array
    {
        return ['N.º Pedido', 'Fecha y hora', 'Cliente', 'Fecha entrega', 'Dirección entrega', 'Pago', 'Productos', 'Total', 'Estado', 'Usuario'];
    }
}
