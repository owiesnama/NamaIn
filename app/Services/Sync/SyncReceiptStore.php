<?php

namespace App\Services\Sync;

use App\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores pushed expense receipts (Design 02 §7.4, Design 03 §5.3) on the same
 * `local` disk / `receipts` prefix the web receipts use, keyed by the
 * device-minted `receipt_public_id`. The upload and the `expense.create`
 * mutation are independent and may arrive in either order, so both the store
 * and the expense handler link by matching this id.
 */
class SyncReceiptStore
{
    private const DISK = 'local';

    private const DIRECTORY = 'receipts';

    /**
     * Store the file and link it to the expense if it has already landed.
     */
    public function store(UploadedFile $file, string $receiptPublicId): string
    {
        $path = $file->storeAs(self::DIRECTORY, $receiptPublicId.'.'.$file->extension(), self::DISK);

        Expense::where('receipt_public_id', $receiptPublicId)->update(['receipt_path' => $path]);

        return $path;
    }

    /**
     * The stored path for a receipt id, if the file was uploaded before the
     * expense mutation landed.
     */
    public function existingPathFor(string $receiptPublicId): ?string
    {
        foreach (Storage::disk(self::DISK)->files(self::DIRECTORY) as $path) {
            if (str_starts_with(basename($path), $receiptPublicId.'.')) {
                return $path;
            }
        }

        return null;
    }
}
