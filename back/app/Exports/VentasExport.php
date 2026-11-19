<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reporte de ventas con dos hojas: listado de ventas y detalle de productos vendidos.
 * La ganancia solo se incluye cuando $conGanancia es true (permiso "Ver Ganancias").
 */
class VentasExport implements WithMultipleSheets
{
    public const MONEY = '"Bs" #,##0.00;[Red]-"Bs" #,##0.00';

    public const COLOR = '1565C0';

    /** @param  array{empresa:string, nit:?string, periodo:string, usuario:?string, generado_por:string}  $meta */
    public function __construct(
        private readonly Collection $ventas,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function sheets(): array
    {
        return [
            new VentasListadoSheet($this->ventas, $this->conGanancia, $this->meta),
            new VentasProductosSheet($this->ventas->where('estado', 'COMPLETADA'), $this->conGanancia, $this->meta),
        ];
    }

    public static function ganancia($venta): float
    {
        return round((float) $venta->detalles->sum(fn ($d) => ($d->precio_venta - $d->precio_compra) * $d->cantidad - $d->descuento), 2);
    }

    /** Encabezado del reporte (filas 1-4). Devuelve la fila donde va la cabecera de la tabla. */
    public static function header(Worksheet $sheet, string $title, array $meta, string $lastCol): int
    {
        $sheet->setCellValue('A1', $meta['empresa']);
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', 'Período: '.$meta['periodo'].($meta['usuario'] ? '   ·   Usuario: '.$meta['usuario'] : ''));
        $sheet->setCellValue('A4', 'Generado el '.now()->format('d/m/Y H:i').' por '.$meta['generado_por'].($meta['nit'] ? '   ·   NIT: '.$meta['nit'] : ''));
        foreach ([1, 2, 3, 4] as $row) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::COLOR);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setRGB('616161');
        $sheet->getRowDimension(1)->setRowHeight(24);

        return 6;
    }

    public static function tableHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType('solid')->getStartColor()->setRGB(self::COLOR);
        $style->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $sheet->getRowDimension((int) preg_replace('/\D/', '', explode(':', $range)[0]))->setRowHeight(22);
    }

    public static function body(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('CFD8DC');
    }

    public static function totals(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType('solid')->getStartColor()->setRGB('E3F2FD');
        $style->getBorders()->getTop()->setBorderStyle('medium')->getColor()->setRGB(self::COLOR);
    }
}
