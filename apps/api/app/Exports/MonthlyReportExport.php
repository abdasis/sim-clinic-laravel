<?php

namespace App\Exports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Susun workbook laporan bulanan mengikuti tata letak laporan spreadsheet
 * yang selama ini dipakai klinik: tabel pasien, rincian dana, rincian
 * pengeluaran, fee terapis, lalu keuntungan bersih.
 *
 * Total memakai formula SUM, bukan angka mati, supaya akuntan yang
 * menyunting barisnya melihat totalnya ikut bergerak.
 */
class MonthlyReportExport
{
    private const HEADER_FILL = '1A1A1A';

    private const MONEY = '#,##0';

    /**
     * @param  array<string, mixed>  $report  hasil ReportService::monthly()
     */
    public function __construct(
        private readonly array $report,
        private readonly string $clinicName,
    ) {}

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Bulanan');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        foreach (['A' => 5, 'B' => 16, 'C' => 22, 'D' => 34, 'E' => 34, 'F' => 12, 'G' => 16, 'H' => 16, 'I' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $row = $this->title($sheet);
        [$row, $firstDataRow, $lastDataRow] = $this->patientTable($sheet, $row);
        $row = $this->paymentSection($sheet, $row + 1);
        [$row, $expenseTotalCell] = $this->expenseSection($sheet, $row + 1);
        $row = $this->commissionSection($sheet, $row + 1);
        $row = $this->netProfit($sheet, $row + 1, $firstDataRow, $lastDataRow, $expenseTotalCell);
        $this->signatures($sheet, $row + 2);

        $sheet->freezePane('A6');

        return $spreadsheet;
    }

    private function title(Worksheet $sheet): int
    {
        $sheet->mergeCells('A1:I1')->setCellValue('A1', mb_strtoupper($this->clinicName));
        $sheet->mergeCells('A2:I2')->setCellValue('A2', 'LAPORAN PASIEN & PENDAPATAN KLINIK');
        $sheet->mergeCells('A3:I3')->setCellValue(
            'A3',
            'Periode '.$this->formatDate($this->report['from']).' — '.$this->formatDate($this->report['to']),
        );

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('555555');
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return 5;
    }

    /**
     * @return array{0: int, 1: int, 2: int} baris berikutnya, baris data pertama & terakhir
     */
    private function patientTable(Worksheet $sheet, int $row): array
    {
        $headers = ['NO', 'TERAPIS', 'NAMA PASIEN', 'TINDAKAN', 'PRODUK', 'TANGGAL', 'TREATMENT (IDR)', 'PRODUK (IDR)', 'TOTAL (IDR)'];
        $sheet->fromArray($headers, null, 'A'.$row);
        $this->headerStyle($sheet, "A{$row}:I{$row}");
        $row++;

        $firstDataRow = $row;

        foreach ($this->report['rows'] as $index => $line) {
            $sheet->fromArray([
                $index + 1,
                $line['therapist_name'] ?? '-',
                $line['patient_name'] ?? '-',
                $line['services'] ?: '-',
                $line['products'] ?: '-',
                $this->formatDate($line['issued_at']),
                $line['treatment_amount'],
                $line['product_amount'],
                $line['total'],
            ], null, 'A'.$row);
            $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setWrapText(true);
            $row++;
        }

        // Baris kosong tetap diberi satu baris data agar formula SUM valid.
        if ($row === $firstDataRow) {
            $sheet->setCellValue('A'.$row, '-');
            $row++;
        }

        $lastDataRow = $row - 1;

        $sheet->setCellValue("F{$row}", 'TOTAL');
        foreach (['G', 'H', 'I'] as $column) {
            $sheet->setCellValue("{$column}{$row}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
        }

        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $this->moneyStyle($sheet, "G{$firstDataRow}:I{$row}");
        $this->thinBorders($sheet, 'A'.($firstDataRow - 1).":I{$row}");

        return [$row + 1, $firstDataRow, $lastDataRow];
    }

    private function paymentSection(Worksheet $sheet, int $row): int
    {
        $row = $this->sectionHeader($sheet, $row, 'RINCIAN DANA (PER METODE BAYAR)');
        $first = $row;

        foreach ($this->report['payments'] as $payment) {
            $sheet->setCellValue("B{$row}", $payment['method_label']);
            $sheet->setCellValue("C{$row}", $payment['total']);
            $row++;
        }

        $sheet->setCellValue("B{$row}", 'TOTAL DANA');
        $sheet->setCellValue("C{$row}", "=SUM(C{$first}:C".($row - 1).')');
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
        $this->moneyStyle($sheet, "C{$first}:C{$row}");
        $this->thinBorders($sheet, "B{$first}:C{$row}");

        return $row + 1;
    }

    /**
     * @return array{0: int, 1: string} baris berikutnya dan sel total pengeluaran
     */
    private function expenseSection(Worksheet $sheet, int $row): array
    {
        $row = $this->sectionHeader($sheet, $row, 'RINCIAN PENGELUARAN');
        $first = $row;

        foreach ($this->report['expenses']['by_category'] as $expense) {
            $sheet->setCellValue("B{$row}", $expense['category_label']);
            $sheet->setCellValue("C{$row}", $expense['total']);
            $row++;
        }

        if ($row === $first) {
            $sheet->setCellValue("B{$row}", '-');
            $sheet->setCellValue("C{$row}", 0);
            $row++;
        }

        $sheet->setCellValue("B{$row}", 'TOTAL PENGELUARAN');
        $sheet->setCellValue("C{$row}", "=SUM(C{$first}:C".($row - 1).')');
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
        $this->moneyStyle($sheet, "C{$first}:C{$row}");
        $this->thinBorders($sheet, "B{$first}:C{$row}");

        return [$row + 1, "C{$row}"];
    }

    private function commissionSection(Worksheet $sheet, int $row): int
    {
        $row = $this->sectionHeader($sheet, $row, 'FEE TERAPIS (PRATINJAU — DIKURANGKAN SETELAH DIBUKUKAN)');
        $first = $row;

        foreach ($this->report['commission']['rows'] as $line) {
            $sheet->setCellValue("B{$row}", $line['therapist_name']);
            $sheet->setCellValue("C{$row}", $line['total']);
            $row++;
        }

        if ($row === $first) {
            $sheet->setCellValue("B{$row}", '-');
            $sheet->setCellValue("C{$row}", 0);
            $row++;
        }

        $sheet->setCellValue("B{$row}", 'TOTAL FEE');
        $sheet->setCellValue("C{$row}", "=SUM(C{$first}:C".($row - 1).')');
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
        $this->moneyStyle($sheet, "C{$first}:C{$row}");
        $this->thinBorders($sheet, "B{$first}:C{$row}");

        return $row + 1;
    }

    private function netProfit(Worksheet $sheet, int $row, int $firstDataRow, int $lastDataRow, string $expenseTotalCell): int
    {
        $sheet->setCellValue("B{$row}", 'KEUNTUNGAN BERSIH (PENDAPATAN - PENGELUARAN)');
        $sheet->setCellValue("C{$row}", "=SUM(I{$firstDataRow}:I{$lastDataRow})-{$expenseTotalCell}");
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("B{$row}:C{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("B{$row}:C{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
        $this->moneyStyle($sheet, "C{$row}");

        return $row + 1;
    }

    private function signatures(Worksheet $sheet, int $row): void
    {
        $labels = [['B', 'Diketahui Oleh'], ['E', 'Disetujui Oleh'], ['H', 'Dibuat Oleh']];

        foreach ($labels as [$column, $label]) {
            $sheet->setCellValue("{$column}{$row}", $label);
            $sheet->setCellValue("{$column}".($row + 4), '(............................)');
            $sheet->getStyle("{$column}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$column}".($row + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    private function sectionHeader(Worksheet $sheet, int $row, string $title): int
    {
        $sheet->mergeCells("B{$row}:C{$row}")->setCellValue("B{$row}", $title);
        $this->headerStyle($sheet, "B{$row}:C{$row}");

        return $row + 1;
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function moneyStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getNumberFormat()->setFormatCode(self::MONEY);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function thinBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }
}
