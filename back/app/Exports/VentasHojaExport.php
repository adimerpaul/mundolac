<?php

namespace App\Exports;

use App\Exports\Concerns\HojaReporte;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Hoja "Ventas": una fila por venta con fecha, hora, quién la registró y sus importes. */
class VentasHojaExport implements FromArray, WithEvents, WithTitle
{
    use HojaReporte;

    private const ENCABEZADOS = ['Nº Venta', 'Fecha', 'Hora', 'Registrado por', 'Tipo pago', 'Efectivo', 'QR',
        'Ítems', 'Productos', 'Subtotal', 'Descuento', 'Total', 'Estado', 'Observación'];

    private int $filaEncabezado = 0;

    private int $filaTotales = 0;

    /** @param  Collection<int, Venta>  $ventas */
    public function __construct(private readonly Collection $ventas, private readonly array $meta) {}

    public function title(): string
    {
        return 'Ventas';
    }

    public function array(): array
    {
        $columnas = count(self::ENCABEZADOS);
        $filas = $this->cabecera('REPORTE DE VENTAS', $columnas);
        $this->filaEncabezado = count($filas) + 1;
        $filas[] = self::ENCABEZADOS;

        foreach ($this->ventas as $venta) {
            $detalles = $venta->detalles;
            $filas[] = [
                $venta->numero,
                $venta->fecha?->format('d/m/Y'),
                $venta->fecha?->format('H:i:s'),
                $venta->usuario_nombre,
                $venta->tipo_pago,
                (float) $venta->monto_efectivo,
                (float) $venta->monto_qr,
                (float) $detalles->sum('cantidad'),
                $detalles->map(fn ($d) => trim($d->cantidad.' '.$d->nombre))->implode(', '),
                (float) $venta->subtotal,
                (float) $venta->descuento,
                (float) $venta->total,
                $venta->estado,
                $venta->observacion,
            ];
        }

        // Los importes solo suman ventas completadas: las anuladas no mueven dinero.
        $completadas = $this->ventas->where('estado', 'COMPLETADA');
        $this->filaTotales = count($filas) + 1;
        $filas[] = [
            'TOTALES', null, null, $completadas->count().' completadas', null,
            (float) $completadas->sum('monto_efectivo'),
            (float) $completadas->sum('monto_qr'),
            (float) $completadas->sum(fn ($v) => $v->detalles->sum('cantidad')),
            null,
            (float) $completadas->sum('subtotal'),
            (float) $completadas->sum('descuento'),
            (float) $completadas->sum('total'),
            null, null,
        ];

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->estilos(
                $event, 'N', $this->filaEncabezado, $this->filaTotales, ['F', 'G', 'J', 'K', 'L']
            ),
        ];
    }
}
