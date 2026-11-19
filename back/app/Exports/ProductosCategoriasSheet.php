<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Resumen por categoría con fórmulas que leen la hoja "Inventario". */
class ProductosCategoriasSheet implements WithEvents, WithTitle
{
    public function __construct(
        private readonly Collection $productos,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function title(): string
    {
        return 'Resumen por categoría';
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $event) => $this->build($event->sheet->getDelegate())];
    }

    private function build(Worksheet $sheet): void
    {
        $headings = ['Categoría', 'Productos', 'Unidades', 'Sin stock', 'Stock bajo', 'Valor inventario (compra)', 'Valor inventario (venta)'];
        if ($this->conGanancia) {
            array_push($headings, 'Ganancia potencial', '% del valor');
        }
        $last = Coordinate::stringFromColumnIndex(count($headings));

        $headerRow = VentasExport::header($sheet, 'Resumen del inventario por categoría', $this->meta + ['periodo' => '', 'usuario' => null], $last);
        $sheet->setCellValue('A3', 'Filtros: '.$this->meta['filtros']);
        $sheet->fromArray($headings, null, "A{$headerRow}");
        VentasExport::tableHeader($sheet, "A{$headerRow}:{$last}{$headerRow}");

        // Rango de la hoja Inventario (la tabla empieza en la fila 7).
        $n = max($this->productos->count(), 1);
        $from = 7;
        $to = $from + $n - 1;
        $r = fn (string $col) => "Inventario!\${$col}\${$from}:\${$col}\${$to}";
        $bajo = ProductosInventarioSheet::STOCK_BAJO;

        $categorias = $this->productos
            ->map(fn ($p) => $p->categoriaRelacion?->nombre ?? $p->categoria ?? 'SIN CATEGORÍA')
            ->unique()->sort()->values();

        $first = $headerRow + 1;
        $totalRow = $headerRow + max($categorias->count(), 1) + 1;
        $row = $headerRow;
        foreach ($categorias as $categoria) {
            $row++;
            $c = "A{$row}";
            $values = [
                $categoria,
                "=COUNTIF({$r('D')},{$c})",
                "=SUMIF({$r('D')},{$c},{$r('F')})",
                "=COUNTIFS({$r('D')},{$c},{$r('F')},\"<=0\")",
                "=COUNTIFS({$r('D')},{$c},{$r('F')},\">0\",{$r('F')},\"<={$bajo}\")",
                "=SUMIF({$r('D')},{$c},{$r('I')})",
                "=SUMIF({$r('D')},{$c},{$r('J')})",
            ];
            if ($this->conGanancia) {
                array_push($values, "=SUMIF({$r('D')},{$c},{$r('M')})", "=IF(\$F\${$totalRow}>0,F{$row}/\$F\${$totalRow},0)");
            }
            $sheet->fromArray($values, null, "A{$row}", true);
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F5F9FD');
            }
        }
        $lastData = $totalRow - 1;

        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        foreach (array_slice(range('B', $last), 0, count($headings) - 1) as $col) {
            $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$first}:{$col}{$lastData})");
        }
        VentasExport::body($sheet, "A{$headerRow}:{$last}{$totalRow}");
        VentasExport::totals($sheet, "A{$totalRow}:{$last}{$totalRow}");

        $sheet->getStyle("B{$first}:E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("B{$first}:E{$totalRow}")->getAlignment()->setHorizontal('center');
        $moneyEnd = $this->conGanancia ? 'H' : 'G';
        $sheet->getStyle("F{$first}:{$moneyEnd}{$totalRow}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
        if ($this->conGanancia) {
            $sheet->getStyle("I{$first}:I{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle("H{$first}:H{$totalRow}")->getFont()->setBold(true)->getColor()->setRGB('2E7D32');
        }
        $sheet->getStyle("D{$first}:D{$lastData}")->getFont()->getColor()->setRGB('C62828');
        $sheet->getStyle("E{$first}:E{$lastData}")->getFont()->getColor()->setRGB('E65100');

        foreach (range(1, count($headings)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);
        $sheet->setAutoFilter("A{$headerRow}:{$last}{$lastData}");
        $sheet->freezePane("B{$first}");
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$this->meta['empresa'].' · Generado por '.$this->meta['generado_por'].'&RPágina &P de &N');
    }
}
