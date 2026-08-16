<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductosVendidosExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $productos) {}

    public function collection(): Collection
    {
        return $this->productos->values()->map(fn ($p, $index) => [
            $index + 1, $p->codigo, $p->nombre, $p->categoria, $p->unidad,
            (float) $p->cantidad, (int) $p->ventas,
            round((float) $p->precio_compra, 2), round((float) $p->precio_venta, 2), round((float) $p->margen, 2),
            (float) $p->descuento, (float) $p->total, (float) $p->ganancia,
            (int) $p->stock_inicial, $p->ultima_venta ? date('d/m/Y H:i', strtotime($p->ultima_venta)) : 'Sin ventas',
        ]);
    }

    public function headings(): array
    {
        return ['#', 'Código', 'Producto', 'Categoría', 'Unidad', 'Cantidad vendida', 'Nº ventas',
            'Precio compra', 'Precio venta', 'Margen unitario', 'Descuento', 'Total vendido', 'Ganancia',
            'Stock actual', 'Última venta'];
    }
}
