<?php

namespace App\Exports;

use App\Exports\Concerns\HojaReporte;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Hoja "Productos": los detalles de las ventas filtradas agregados por producto,
 * ordenados por total vendido. Solo cuenta ventas COMPLETADAS.
 */
class VentasProductosHojaExport implements FromArray, WithEvents, WithTitle
{
    use HojaReporte;

    private const ENCABEZADOS = ['#', 'Código', 'Producto', 'Categoría', 'Unidad', 'Cantidad', 'Nº ventas',
        'Precio compra', 'Precio venta', 'Descuento', 'Total', 'Ganancia'];

    private int $filaEncabezado = 0;

    private int $filaTotales = 0;

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $agregados = null;

    /** @param  Collection<int, Venta>  $ventas */
    public function __construct(private readonly Collection $ventas, private readonly array $meta) {}

    public function title(): string
    {
        return 'Productos';
    }

    public function array(): array
    {
        $columnas = count(self::ENCABEZADOS);
        $filas = $this->cabecera('PRODUCTOS VENDIDOS', $columnas);
        $this->filaEncabezado = count($filas) + 1;
        $filas[] = self::ENCABEZADOS;

        foreach ($this->productos() as $indice => $producto) {
            $filas[] = [
                $indice + 1, $producto['codigo'], $producto['nombre'], $producto['categoria'], $producto['unidad'],
                $producto['cantidad'], $producto['ventas'],
                $producto['cantidad'] ? round($producto['costo'] / $producto['cantidad'], 2) : 0,
                $producto['cantidad'] ? round($producto['bruto'] / $producto['cantidad'], 2) : 0,
                $producto['descuento'], $producto['total'], $producto['ganancia'],
            ];
        }

        $productos = $this->productos();
        $this->filaTotales = count($filas) + 1;
        $filas[] = [
            'TOTALES', null, $productos->count().' productos', null, null,
            $productos->sum('cantidad'), null, null, null,
            round($productos->sum('descuento'), 2),
            round($productos->sum('total'), 2),
            round($productos->sum('ganancia'), 2),
        ];

        return $filas;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function productos(): Collection
    {
        if ($this->agregados !== null) {
            return $this->agregados;
        }

        return $this->agregados = $this->ventas->where('estado', 'COMPLETADA')
            ->flatMap(fn ($venta) => $venta->detalles->map(fn ($d) => ['venta_id' => $venta->id, 'detalle' => $d]))
            ->groupBy(fn ($fila) => $fila['detalle']->producto_id ?? 'S/P-'.$fila['detalle']->nombre)
            ->map(function ($filas) {
                $detalles = $filas->pluck('detalle');
                $primero = $detalles->first();
                $cantidad = (float) $detalles->sum('cantidad');
                $costo = (float) $detalles->sum(fn ($d) => $d->precio_compra * $d->cantidad);
                $bruto = (float) $detalles->sum(fn ($d) => $d->precio_venta * $d->cantidad);
                $descuento = (float) $detalles->sum('descuento');

                return [
                    'codigo' => $primero->codigo,
                    'nombre' => $primero->nombre,
                    'categoria' => $primero->categoria,
                    'unidad' => $primero->unidad,
                    'cantidad' => $cantidad,
                    'ventas' => $filas->pluck('venta_id')->unique()->count(),
                    'costo' => $costo,
                    'bruto' => $bruto,
                    'descuento' => round($descuento, 2),
                    'total' => round((float) $detalles->sum('total'), 2),
                    'ganancia' => round($bruto - $costo - $descuento, 2),
                ];
            })
            ->sortByDesc('total')->values();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->estilos(
                $event, 'L', $this->filaEncabezado, $this->filaTotales, ['H', 'I', 'J', 'K', 'L']
            ),
        ];
    }
}
