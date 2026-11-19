<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosInventarioSheet implements WithEvents, WithTitle
{
    public const STOCK_BAJO = 5;

    public function __construct(
        private readonly Collection $productos,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function title(): string
    {
        return 'Inventario';
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $event) => $this->build($event->sheet->getDelegate())];
    }

    private function build(Worksheet $sheet): void
    {
        // A # | B Código | C Producto | D Categoría | E Unidad | F Cantidad | G P. compra | H P. venta
        // I Valor compra | J Valor venta | [K Margen unit. | L Margen % | M Ganancia potencial] | Estado
        $headings = ['#', 'Código', 'Producto', 'Categoría', 'Unidad', 'Cantidad', 'Precio compra', 'Precio venta',
            'Valor inventario (compra)', 'Valor inventario (venta)'];
        if ($this->conGanancia) {
            array_push($headings, 'Margen unitario', 'Margen %', 'Ganancia potencial');
        }
        $headings[] = 'Estado stock';
        $last = Coordinate::stringFromColumnIndex(count($headings));

        $headerRow = VentasExport::header($sheet, 'Inventario de productos', $this->meta + ['periodo' => '', 'usuario' => null], $last);
        $sheet->setCellValue('A3', 'Filtros: '.$this->meta['filtros'].'   ·   Productos: '.$this->productos->count());

        $sheet->fromArray($headings, null, "A{$headerRow}");
        VentasExport::tableHeader($sheet, "A{$headerRow}:{$last}{$headerRow}");
        $first = $headerRow + 1;
        $row = $headerRow;

        foreach ($this->productos->values() as $i => $p) {
            $row++;
            $values = [
                $i + 1, $p->codigo, $p->nombre, $p->categoriaRelacion?->nombre ?? $p->categoria ?? 'SIN CATEGORÍA', $p->unidad,
                (int) $p->stock_inicial, round((float) $p->precio_compra, 2), round((float) $p->precio_venta, 2),
                "=F{$row}*G{$row}", "=F{$row}*H{$row}",
            ];
            if ($this->conGanancia) {
                array_push($values, "=H{$row}-G{$row}", "=IF(H{$row}>0,(H{$row}-G{$row})/H{$row},0)", "=J{$row}-I{$row}");
            }
            $values[] = "=IF(F{$row}<=0,\"SIN STOCK\",IF(F{$row}<=".self::STOCK_BAJO.',"BAJO","DISPONIBLE"))';
            $sheet->fromArray($values, null, "A{$row}", true);
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F5F9FD');
            }
        }
        $lastData = max($row, $first);

        // Totales con SUBTOTAL(109/103): se recalculan solos al usar el autofiltro.
        $totalRow = $lastData + 1;
        $sheet->setCellValue("A{$totalRow}", "=\"TOTAL (\"&SUBTOTAL(103,C{$first}:C{$lastData})&\" productos visibles)\"");
        $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
        $sumCols = $this->conGanancia ? ['F', 'I', 'J', 'M'] : ['F', 'I', 'J'];
        foreach ($sumCols as $col) {
            $sheet->setCellValue("{$col}{$totalRow}", "=SUBTOTAL(109,{$col}{$first}:{$col}{$lastData})");
        }
        if ($this->conGanancia) {
            $sheet->setCellValue("L{$totalRow}", "=IF(J{$totalRow}>0,M{$totalRow}/J{$totalRow},0)");
        }
        VentasExport::body($sheet, "A{$headerRow}:{$last}{$totalRow}");
        VentasExport::totals($sheet, "A{$totalRow}:{$last}{$totalRow}");

        // Formatos
        $sheet->getStyle("G{$first}:J{$totalRow}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
        $sheet->getStyle("F{$first}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        if ($this->conGanancia) {
            $sheet->getStyle("K{$first}:K{$totalRow}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
            $sheet->getStyle("M{$first}:M{$totalRow}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
            $sheet->getStyle("L{$first}:L{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle("M{$first}:M{$totalRow}")->getFont()->setBold(true)->getColor()->setRGB('2E7D32');
        }
        $sheet->getStyle("A{$first}:A{$lastData}")->getAlignment()->setHorizontal('center');
        $sheet->getStyle("E{$first}:F{$lastData}")->getAlignment()->setHorizontal('center');
        $sheet->getStyle("{$last}{$first}:{$last}{$lastData}")->getAlignment()->setHorizontal('center');
        $sheet->getStyle("{$last}{$first}:{$last}{$lastData}")->getFont()->setBold(true)->setSize(9);

        // Colores automáticos según cantidad (siguen funcionando si se edita la celda).
        $this->colorByStock($sheet, "F{$first}:F{$lastData}", "\$F{$first}");
        $this->colorByStock($sheet, "{$last}{$first}:{$last}{$lastData}", "\$F{$first}");

        foreach (range(1, count($headings)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
        foreach (['I', 'J'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(16);
        }
        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        $sheet->setAutoFilter("A{$headerRow}:{$last}{$lastData}");
        $sheet->freezePane("D{$first}");
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$this->meta['empresa'].' · Generado por '.$this->meta['generado_por'].'&RPágina &P de &N');
        $sheet->setSelectedCell("A{$first}");
    }

    private function colorByStock(Worksheet $sheet, string $range, string $ref): void
    {
        $sinStock = (new Conditional)->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition("{$ref}<=0");
        $sinStock->getStyle()->getFont()->getColor()->setRGB('C62828');
        $sinStock->getStyle()->getFill()->setFillType('solid')->getEndColor()->setRGB('FFEBEE');

        $bajo = (new Conditional)->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition("{$ref}<=".self::STOCK_BAJO);
        $bajo->getStyle()->getFont()->getColor()->setRGB('E65100');
        $bajo->getStyle()->getFill()->setFillType('solid')->getEndColor()->setRGB('FFF3E0');

        $sheet->getStyle($range)->setConditionalStyles([$sinStock, $bajo]);
    }
}
