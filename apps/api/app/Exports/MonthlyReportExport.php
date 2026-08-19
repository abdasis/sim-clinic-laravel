<?php

namespace App\Exports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Susun workbook laporan bulanan mengikuti tata letak laporan spreadsheet
 * yang selama ini dipakai klinik: kepala laporan, angka pembuka, tabel
 * kunjungan (lengkap dengan fee terapis dan total bersih per baris), rincian
 * dana per metode bayar, rincian pengeluaran, fee terapis, keuntungan bersih,
 * lalu blok tanda tangan.
 *
 * Warnanya bukan hiasan: tiap blok punya satu warna tetap yang sama di layar,
 * di PDF, dan di sini — mata langsung tahu sedang membaca uang masuk, uang
 * keluar, atau fee tanpa harus membaca judulnya lebih dulu. Angka tetap hitam
 * di atas latar terang; warna hanya dipakai di kepala blok dan garis, supaya
 * tercetak hitam-putih pun susunannya masih terbaca.
 *
 * Total memakai formula SUM, bukan angka mati, supaya akuntan yang menyunting
 * barisnya melihat totalnya ikut bergerak.
 */
class MonthlyReportExport
{
    /** Kolom terakhir tabel kunjungan; dipakai untuk semua merge selebar halaman. */
    private const LAST_COLUMN = 'L';

    private const INK = '0B3B2E';

    private const BRAND = '007A55';

    private const BRAND_TINT = 'E4F5ED';

    private const ZEBRA = 'F5FBF8';

    private const GRID = 'D3E5DD';

    private const MUTED = '6B7F78';

    /** Warna per blok: [isi kepala, isi baris]. Sama persis dengan versi PDF. */
    private const FUND = ['1F5FA8', 'EAF1FA'];

    private const EXPENSE = ['B34A22', 'FDF0EA'];

    private const FEE = ['8A6100', 'FBF4E3'];

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
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->columnWidths($sheet);

        $row = $this->titleBand($sheet);
        $row = $this->summaryStrip($sheet, $row);
        [$row, $firstDataRow, $lastDataRow] = $this->visitTable($sheet, $row);
        $row = $this->breakdown($sheet, $row + 1, 'RINCIAN DANA MASUK (PER METODE BAYAR)', self::FUND, $this->fundRows(), 'TOTAL DANA MASUK');
        [$row, $expenseTotalCell] = $this->expenseBlock($sheet, $row + 1);
        $row = $this->breakdown($sheet, $row + 1, 'FEE & KOMISI STAF (PRATINJAU)', self::FEE, $this->feeRows(), 'TOTAL FEE', 'Fee di atas belum memotong keuntungan; ia baru jadi pengeluaran setelah dibukukan.');
        $row = $this->netProfit($sheet, $row + 1, $firstDataRow, $lastDataRow, $expenseTotalCell);
        $this->signatures($sheet, $row + 2);

        $this->printSetup($sheet, $firstDataRow);

        return $spreadsheet;
    }

    private function columnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 5, 'B' => 12, 'C' => 18, 'D' => 22, 'E' => 13, 'F' => 30,
            'G' => 30, 'H' => 15, 'I' => 15, 'J' => 15, 'K' => 14, 'L' => 16,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function titleBand(Worksheet $sheet): int
    {
        $span = 'A%1$d:'.self::LAST_COLUMN.'%1$d';

        $sheet->mergeCells(sprintf($span, 1))->setCellValue('A1', mb_strtoupper($this->clinicName));
        $sheet->mergeCells(sprintf($span, 2))->setCellValue('A2', 'LAPORAN PASIEN & PENDAPATAN KLINIK');
        $sheet->mergeCells(sprintf($span, 3))->setCellValue(
            'A3',
            'Periode '.$this->formatDate($this->report['from']).'  s.d.  '.$this->formatDate($this->report['to']),
        );

        $band = $sheet->getStyle('A1:'.self::LAST_COLUMN.'3');
        $band->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INK);
        $band->getFont()->getColor()->setRGB('FFFFFF');
        $band->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('BBD8CC');
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(18);

        return 5;
    }

    /**
     * Empat angka pembuka: pendapatan, pengeluaran, keuntungan, kunjungan.
     * Yang dicari orang pertama kali ada di baris pertama, bukan di ujung.
     */
    private function summaryStrip(Worksheet $sheet, int $row): int
    {
        $summary = $this->report['summary'];
        $cards = [
            ['A', 'C', 'PENDAPATAN', $this->report['totals']['revenue'], self::BRAND, self::BRAND_TINT],
            ['D', 'F', 'PENGELUARAN', $this->report['expenses']['total'], self::EXPENSE[0], self::EXPENSE[1]],
            ['G', 'I', 'KEUNTUNGAN BERSIH', $this->report['net_profit'], self::INK, self::BRAND_TINT],
            ['J', self::LAST_COLUMN, 'KUNJUNGAN / PASIEN BARU', $summary['visits'].' / '.$summary['new_patients'], self::FUND[0], self::FUND[1]],
        ];

        foreach ($cards as [$start, $end, $label, $value, $ink, $tint]) {
            $sheet->mergeCells("{$start}{$row}:{$end}{$row}")->setCellValue("{$start}{$row}", $label);
            $sheet->mergeCells("{$start}".($row + 1).":{$end}".($row + 1))->setCellValue("{$start}".($row + 1), $value);

            $sheet->getStyle("{$start}{$row}")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB($ink);
            $sheet->getStyle("{$start}".($row + 1))->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::INK);

            $block = $sheet->getStyle("{$start}{$row}:{$end}".($row + 1));
            $block->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tint);
            $block->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $block->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($ink);

            if (is_float($value)) {
                $sheet->getStyle("{$start}".($row + 1))->getNumberFormat()->setFormatCode('"Rp "'.self::MONEY);
            }
        }

        $sheet->getRowDimension($row + 1)->setRowHeight(24);

        return $row + 3;
    }

    /**
     * @return array{0: int, 1: int, 2: int} baris berikutnya, baris data pertama & terakhir
     */
    private function visitTable(Worksheet $sheet, int $row): array
    {
        // Kolom PRODUK muncul dua kali dengan arti berbeda — nama barang dan
        // nilainya; yang berisi uang diberi tanda (Rp) supaya tidak tertukar.
        $headers = [
            'NO', 'TANGGAL', 'STAF', 'NAMA PASIEN', 'BAYAR', 'TINDAKAN', 'PRODUK',
            'TREATMENT (Rp)', 'PRODUK (Rp)', 'TOTAL (Rp)', 'FEE & KOMISI (Rp)', 'TOTAL BERSIH (Rp)',
        ];

        $sheet->fromArray($headers, null, 'A'.$row);
        $header = $sheet->getStyle("A{$row}:".self::LAST_COLUMN.$row);
        $header->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
        $header->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $headerRow = $row;
        $row++;

        $firstDataRow = $row;
        $row = $this->visitRows($sheet, $row);
        $lastDataRow = $row - 1;

        $this->visitTotals($sheet, $row, $firstDataRow, $lastDataRow);

        // Saringan bawaan Excel: menyisir satu terapis atau satu metode bayar
        // tanpa harus menyalin tabelnya ke lembar lain.
        $sheet->setAutoFilter("A{$headerRow}:".self::LAST_COLUMN.$lastDataRow);

        $this->moneyStyle($sheet, "H{$firstDataRow}:".self::LAST_COLUMN.$row);
        $sheet->getStyle("A{$firstDataRow}:".self::LAST_COLUMN.$row)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRID);

        return [$row + 1, $firstDataRow, $lastDataRow];
    }

    private function visitRows(Worksheet $sheet, int $row): int
    {
        $first = $row;

        foreach ($this->report['rows'] as $index => $line) {
            $sheet->fromArray([
                $index + 1,
                $this->formatDate($line['issued_at']),
                $line['therapist_name'] ?? '-',
                $line['patient_name'] ?? '-',
                $line['payment_label'] ?? '-',
                $line['services'] ?: '-',
                $line['products'] ?: '-',
                $line['treatment_amount'],
                $line['product_amount'],
                $line['total'],
                $line['fee_amount'],
                $line['net_amount'],
            ], null, 'A'.$row);

            $sheet->getStyle("A{$row}:".self::LAST_COLUMN.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("F{$row}:G{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Baris selang-seling: menyusuri satu baris sampai kolom paling
            // kanan tanpa tersasar ke baris tetangga.
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$row}:".self::LAST_COLUMN.$row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ZEBRA);
            }

            $row++;
        }

        // Rentang kosong tetap butuh satu baris agar formula SUM tetap sah.
        if ($row === $first) {
            $sheet->mergeCells("A{$row}:".self::LAST_COLUMN.$row)
                ->setCellValue("A{$row}", 'Tidak ada transaksi lunas pada periode ini.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED);
            $row++;
        }

        return $row;
    }

    private function visitTotals(Worksheet $sheet, int $row, int $firstDataRow, int $lastDataRow): void
    {
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:G{$row}");

        foreach (['H', 'I', 'J', 'K', self::LAST_COLUMN] as $column) {
            $sheet->setCellValue("{$column}{$row}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
        }

        $total = $sheet->getStyle("A{$row}:".self::LAST_COLUMN.$row);
        $total->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::INK);
        $total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND_TINT);
        $total->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BRAND);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    /**
     * Satu blok rincian dua kolom: label + nilai, kepala berwarna, total tebal.
     *
     * @param  array{0: string, 1: string}  $palette
     * @param  array<int, array{0: string, 1: float}>  $rows
     */
    private function breakdown(Worksheet $sheet, int $row, string $title, array $palette, array $rows, string $totalLabel, ?string $note = null): int
    {
        [$ink, $tint] = $palette;

        $sheet->mergeCells("B{$row}:E{$row}")->setCellValue("B{$row}", $title);
        $head = $sheet->getStyle("B{$row}:E{$row}");
        $head->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($ink);
        $head->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $first = $row;

        foreach ($rows as [$label, $value]) {
            $sheet->mergeCells("B{$row}:D{$row}")->setCellValue("B{$row}", $label);
            $sheet->setCellValue("E{$row}", $value);
            $row++;
        }

        if ($row === $first) {
            $sheet->mergeCells("B{$row}:D{$row}")->setCellValue("B{$row}", 'Belum ada catatan.');
            $sheet->getStyle("B{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED);
            $sheet->setCellValue("E{$row}", 0);
            $row++;
        }

        $sheet->mergeCells("B{$row}:D{$row}")->setCellValue("B{$row}", $totalLabel);
        $sheet->setCellValue("E{$row}", "=SUM(E{$first}:E".($row - 1).')');

        $totalStyle = $sheet->getStyle("B{$row}:E{$row}");
        $totalStyle->getFont()->setBold(true)->getColor()->setRGB($ink);
        $totalStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tint);

        $this->moneyStyle($sheet, "E{$first}:E{$row}");
        $sheet->getStyle("B{$first}:E{$row}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRID);
        $sheet->getStyle("B{$first}:E{$row}")->getAlignment()->setIndent(1);

        if ($note !== null) {
            $row++;
            $sheet->mergeCells("B{$row}:E{$row}")->setCellValue("B{$row}", $note);
            $sheet->getStyle("B{$row}")->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB(self::MUTED);
        }

        return $row + 1;
    }

    /**
     * @return array{0: int, 1: string} baris berikutnya dan sel total pengeluaran
     */
    private function expenseBlock(Worksheet $sheet, int $row): array
    {
        $rows = collect($this->report['expenses']['by_category'])
            ->map(fn (array $expense) => [$expense['category_label'], (float) $expense['total']])
            ->all();

        $next = $this->breakdown($sheet, $row, 'RINCIAN PENGELUARAN', self::EXPENSE, $rows, 'TOTAL PENGELUARAN');

        // Barisnya: kepala + isi (minimal satu) + total, tepat sebelum $next.
        return [$next, 'E'.($next - 1)];
    }

    /** @return array<int, array{0: string, 1: float}> */
    private function fundRows(): array
    {
        return collect($this->report['payments'])
            ->map(fn (array $payment) => [$payment['method_label'], (float) $payment['total']])
            ->all();
    }

    /** @return array<int, array{0: string, 1: float}> */
    private function feeRows(): array
    {
        return collect($this->report['commission']['rows'])
            ->map(fn (array $line) => [$line['therapist_name'], (float) $line['total']])
            ->all();
    }

    private function netProfit(Worksheet $sheet, int $row, int $firstDataRow, int $lastDataRow, string $expenseTotalCell): int
    {
        $sheet->mergeCells("B{$row}:D{$row}")->setCellValue("B{$row}", 'KEUNTUNGAN BERSIH  (PENDAPATAN − PENGELUARAN)');
        $sheet->setCellValue("E{$row}", "=SUM(J{$firstDataRow}:J{$lastDataRow})-{$expenseTotalCell}");

        $style = $sheet->getStyle("B{$row}:E{$row}");
        $style->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INK);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('"Rp "'.self::MONEY);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(28);

        return $row + 1;
    }

    /** Empat kolom tanda tangan, mengikuti lembar yang biasa diedarkan klinik. */
    private function signatures(Worksheet $sheet, int $row): void
    {
        $columns = [
            ['B', 'Diketahui Oleh'],
            ['D', 'Disetujui Oleh'],
            ['G', 'Staf'],
            ['J', 'Dibukukan dan dibuat oleh'],
        ];

        foreach ($columns as [$column, $label]) {
            $sheet->setCellValue("{$column}{$row}", $label);
            $sheet->setCellValue("{$column}".($row + 4), '(............................)');

            $sheet->getStyle("{$column}{$row}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB(self::INK);
            $sheet->getStyle("{$column}".($row + 4))->getFont()->getColor()->setRGB(self::MUTED);

            foreach ([$row, $row + 4] as $line) {
                $sheet->getStyle("{$column}{$line}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }
    }

    /**
     * Laporan ini memang dicetak dan diedarkan, jadi berkasnya sudah siap
     * cetak: mendatar, muat selebar satu halaman, kepala tabel berulang di
     * tiap halaman lanjutan.
     */
    private function printSetup(Worksheet $sheet, int $firstDataRow): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setFitToWidth(1)->setFitToHeight(0);
        $setup->setRowsToRepeatAtTopByStartAndEnd($firstDataRow - 1, $firstDataRow - 1);

        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.35)->setRight(0.35);
        $sheet->freezePane('A'.$firstDataRow);
        $sheet->setSelectedCell('A1');
    }

    private function moneyStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getNumberFormat()->setFormatCode(self::MONEY);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }
}
