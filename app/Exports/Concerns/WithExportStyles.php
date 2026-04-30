<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait WithExportStyles
{
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setName('Cairo');
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setSize(12);

        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('F3F4F6');

        $sheet->getStyle("A1:{$lastColumn}1")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('D1D5DB');

        if (app()->getLocale() === 'ar') {
            $sheet->setRightToLeft(true);
        }

        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
