<?php

namespace App\Exports;

use App\Models\Venta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro de Excel del módulo de ventas: una hoja con el detalle de cada venta
 * y otra con los productos vendidos agregados. Ambas comparten la cabecera con
 * los filtros aplicados y quién generó el reporte.
 */
class VentasReporteExport implements WithMultipleSheets
{
    /** @param  Collection<int, Venta>  $ventas */
    public function __construct(private readonly Collection $ventas, private readonly array $meta) {}

    public function sheets(): array
    {
        return [
            new VentasHojaExport($this->ventas, $this->meta),
            new VentasProductosHojaExport($this->ventas, $this->meta),
        ];
    }
}
