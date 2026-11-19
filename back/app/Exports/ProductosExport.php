<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Inventario de productos: hoja de detalle (con autofiltro y totales que se
 * recalculan al filtrar) y hoja de resumen por categoría con fórmulas.
 * Las columnas de margen/ganancia solo salen con el permiso "Ver Ganancias".
 *
 * @param  array{empresa:string, nit:?string, filtros:string, generado_por:string}  $meta
 */
class ProductosExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    public function __construct(
        private readonly Collection $productos,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function sheets(): array
    {
        return [
            new ProductosInventarioSheet($this->productos, $this->conGanancia, $this->meta),
            new ProductosCategoriasSheet($this->productos, $this->conGanancia, $this->meta),
        ];
    }
}
