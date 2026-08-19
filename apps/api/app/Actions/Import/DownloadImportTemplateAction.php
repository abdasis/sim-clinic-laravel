<?php

namespace App\Actions\Import;

use App\Enums\CategorizableType;
use App\Models\Category;
use App\Models\Unit;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Susun template impor: lembar isian, lembar contoh, dan lembar daftar
 * kategori.
 *
 * Contohnya sengaja TIDAK diletakkan di lembar isian. Impor membaca lembar
 * pertama apa adanya, jadi contoh yang duduk di sana akan ikut masuk sebagai
 * layanan sungguhan bagi siapa pun yang menambahkan barisnya di bawah contoh
 * tanpa menghapusnya lebih dulu.
 *
 * Kolom kategori diberi dropdown yang menunjuk lembar kategori, jadi kategori
 * yang sudah ada dipilih alih-alih diketik ulang — salah ketik akan melahirkan
 * kategori kembar yang harus dirapikan belakangan.
 *
 * Daftarnya ikut keadaan saat template diunduh. Kategori yang dibuat setelah
 * itu tidak muncul sampai template diunduh lagi; mengetiknya manual tetap
 * diterima saat impor.
 */
class DownloadImportTemplateAction
{
    private const HEADER_FILL = '1A1A1A';

    private const SHEET_CATEGORY = 'Kategori';

    private const SHEET_UNIT = 'Satuan';

    private const SHEET_EXAMPLE = 'Contoh Pengisian';

    /** @var array<string, array{header: array<int, string>, samples: array<int, array<int, string>>}> */
    private const LAYOUT = [
        'service' => [
            'header' => ImportServicesAction::HEADER,
            'samples' => [
                ['Facial Basic', 'Facial & Peeling', 'Pembersihan wajah menyeluruh', '150000', '45', 'active'],
                ['Glow Booster', 'Skinbooster', '', '500000', '60', 'active'],
            ],
        ],
        'product' => [
            'header' => ImportProductsAction::HEADER,
            'samples' => [
                ['Serum Vitamin C', 'Serum', 'botol', '3', '150000', 'active'],
                ['Cleansing Toner', 'Toner', 'botol', '5', '65000', 'active'],
            ],
        ],
    ];

    public function handle(string $type): Spreadsheet
    {
        $layout = self::LAYOUT[$type];
        $categories = $this->categoryNames($type);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $this->writeTemplate($sheet, $layout);
        $this->writeExamples($spreadsheet, $layout);
        $this->writeList($spreadsheet, self::SHEET_CATEGORY, 'Kategori tersedia', $categories);
        $this->attachListDropdown($sheet, $layout['header'], 'kategori', self::SHEET_CATEGORY, count($categories));

        // Satuan hanya milik produk; template layanan tidak punya kolomnya.
        $units = $type === 'product' ? $this->unitNames() : [];

        if ($units !== []) {
            $this->writeList($spreadsheet, self::SHEET_UNIT, 'Satuan tersedia', $units);
            $this->attachListDropdown($sheet, $layout['header'], 'satuan', self::SHEET_UNIT, count($units));
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  array{header: array<int, string>, samples: array<int, array<int, string>>}  $layout
     */
    private function writeTemplate(Worksheet $sheet, array $layout): void
    {
        $sheet->fromArray($layout['header'], null, 'A1');

        $last = $this->columnLetter(count($layout['header']));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF'.self::HEADER_FILL);

        foreach (range(1, count($layout['header'])) as $index) {
            $sheet->getColumnDimension($this->columnLetter($index))->setWidth(22);
        }

        $sheet->freezePane('A2');
    }

    /**
     * Lembar contoh: format pengisian tanpa risiko ikut terimpor.
     *
     * @param  array{header: array<int, string>, samples: array<int, array<int, string>>}  $layout
     */
    private function writeExamples(Spreadsheet $spreadsheet, array $layout): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::SHEET_EXAMPLE);

        $sheet->fromArray($layout['header'], null, 'A1');
        $sheet->fromArray($layout['samples'], null, 'A2');

        $last = $this->columnLetter(count($layout['header']));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->getStyle('A2:'.$last.(1 + count($layout['samples'])))
            ->getFont()->getColor()->setARGB('FF9AA0A6');

        foreach (range(1, count($layout['header'])) as $index) {
            $sheet->getColumnDimension($this->columnLetter($index))->setWidth(22);
        }
    }

    /**
     * Lembar daftar acuan (kategori, satuan) yang jadi sumber dropdown.
     *
     * @param  array<int, string>  $values
     */
    private function writeList(Spreadsheet $spreadsheet, string $title, string $heading, array $values): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->setCellValue('A1', $heading);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(28);

        foreach ($values as $index => $name) {
            $sheet->setCellValue('A'.($index + 2), $name);
        }
    }

    /** @param  array<int, string>  $header */
    private function attachListDropdown(
        Worksheet $sheet,
        array $header,
        string $columnName,
        string $listSheet,
        int $count,
    ): void {
        $position = array_search($columnName, $header, true);

        if ($position === false || $count === 0) {
            return;
        }

        $column = $this->columnLetter($position + 1);

        $validation = $sheet->getCell($column.'2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(false)
            ->setFormula1('='.$listSheet.'!$A$2:$A$'.($count + 1));

        // Dropdown berlaku sampai baris 501: batas baris impor ditambah header.
        foreach (range(2, ParseImportFileAction::MAX_ROWS + 1) as $row) {
            $sheet->getCell($column.$row)->setDataValidation(clone $validation);
        }
    }

    /** @return array<int, string> */
    private function unitNames(): array
    {
        return Unit::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @return array<int, string> */
    private function categoryNames(string $type): array
    {
        return Category::query()
            ->where('type', CategorizableType::from($type))
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }
}
