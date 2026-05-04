<?php

namespace App\Imports\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

trait ParsesSpreadsheetDates
{
    protected function parseSpreadsheetDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value) && (float) $value >= 25569) {
            return Carbon::instance(Date::excelToDateTimeObject((float) $value));
        }

        $value = trim((string) $value);

        foreach ($this->spreadsheetDateFormats() as $format) {
            $date = $this->createDateFromFormat($format, $value);

            if ($date) {
                return $date;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private function spreadsheetDateFormats(): array
    {
        return [
            'd/m/Y',
            'j/n/Y',
            'd-m-Y',
            'j-n-Y',
            'd.m.Y',
            'j.n.Y',
            'Y-m-d',
            'Y/m/d',
            'Y.m.d',
            'm/d/Y',
            'n/j/Y',
        ];
    }

    private function createDateFromFormat(string $format, string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('!'.$format, $value);
        } catch (Throwable) {
            return null;
        }

        $errors = Carbon::getLastErrors();

        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        return $date === false ? null : $date;
    }
}
