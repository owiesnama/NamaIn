<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CsvSampleGenerator
{
    /**
     * Generate and download a CSV sample file.
     */
    public function generate(string $filename, array $headers, array $sampleData): BinaryFileResponse
    {
        $filePath = storage_path("app/public/{$filename}");

        $handle = fopen($filePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, $sampleData);
        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend();
    }
}
