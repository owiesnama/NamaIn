<?php

namespace App\Imports\Concerns;

use Maatwebsite\Excel\Validators\Failure;

trait TracksImportProgress
{
    private int $rowCount = 0;

    /** @var Failure[] */
    private array $failures = [];

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->failures[] = $failure;
        }
    }

    /** @return Failure[] */
    public function failures(): array
    {
        return $this->failures;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    protected function incrementRowCount(): void
    {
        $this->rowCount++;
    }
}
