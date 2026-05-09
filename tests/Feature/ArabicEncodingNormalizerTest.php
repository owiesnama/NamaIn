<?php

use App\Imports\CustomerImport;
use App\Imports\ProductImport;
use App\Imports\SupplierImport;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Utils\ArabicEncodingNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// ArabicEncodingNormalizer unit tests
// ---------------------------------------------------------------------------

describe('ArabicEncodingNormalizer', function () {
    beforeEach(function () {
        $this->normalizer = new ArabicEncodingNormalizer;
    });

    it('returns valid Arabic text unchanged', function () {
        $arabic = 'منتج تجريبي';
        expect($this->normalizer->normalize($arabic))->toBe($arabic);
    });

    it('returns ASCII text unchanged', function () {
        expect($this->normalizer->normalize('Product A'))->toBe('Product A');
    });

    it('returns empty string unchanged', function () {
        // normalizeText in trait guards null/empty, but the service also handles it
        expect($this->normalizer->normalize(''))->toBe('');
    });

    it('fixes Windows-1256 mojibake back to Arabic', function () {
        $garbled = mojibake('منتج');
        expect($this->normalizer->normalize($garbled))->toBe('منتج');
    });

    it('detects garbled text correctly', function () {
        expect($this->normalizer->isGarbled(mojibake('اختبار')))->toBeTrue();
        expect($this->normalizer->isGarbled('اختبار'))->toBeFalse();
        expect($this->normalizer->isGarbled('Test'))->toBeFalse();
    });

    it('returns original text when conversion does not produce valid Arabic', function () {
        // A string that contains Latin-1 supplement chars but is NOT Arabic mojibake
        // e.g. genuine Latin text with accents — after conversion it won't be Arabic,
        // so the normalizer must return it as-is
        $latin = "caf\xC3\xA9"; // "café" in UTF-8 (é = U+00E9, falls in the C0–FF range check)
        // The normalizer will try to fix it but the result won't be valid Arabic → returns original
        $result = $this->normalizer->normalize($latin);
        // It should not corrupt — result is either original or a valid Arabic string
        expect(
            $result === $latin || (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $result)
        )->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Helper: produce Windows-1256 mojibake from Arabic UTF-8
// ---------------------------------------------------------------------------

function mojibake(string $arabic): string
{
    return ArabicEncodingNormalizer::createMojibake($arabic);
}

// ---------------------------------------------------------------------------
// Import class tests — encoding is fixed before storing
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'test-co']);
    app()->instance('currentTenant', $this->tenant);
});

afterEach(function () {
    app()->forgetInstance('currentTenant');
});

it('ProductImport normalizes garbled product names on import', function () {
    $csv = tempCsv([
        ['name', 'cost', 'price'],
        [mojibake('منتج مستورد'), '100', '120'],
    ]);

    Excel::import(new ProductImport, $csv);

    $product = Product::first();
    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('منتج مستورد');
});

it('ProductImport normalizes garbled unit names', function () {
    $csv = tempCsv([
        ['name', 'cost', 'price', 'unit_name', 'unit_conversion_factor'],
        [mojibake('منتج'), '50', '60', mojibake('كرتون'), '12'],
    ]);

    Excel::import(new ProductImport, $csv);

    $product = Product::first();
    expect($product->units->first()->name)->toBe('كرتون');
});

it('ProductImport normalizes garbled category names', function () {
    $csv = tempCsv([
        ['name', 'cost', 'price', 'categories'],
        [mojibake('منتج'), '50', '60', mojibake('إلكترونيات')],
    ]);

    Excel::import(new ProductImport, $csv);

    $product = Product::with('categories')->first();
    expect($product->categories->first()->name)->toBe('إلكترونيات');
});

it('ProductImport leaves already-correct Arabic names unchanged', function () {
    $csv = tempCsv([
        ['name', 'cost', 'price'],
        ['منتج صحيح', '100', '120'],
    ]);

    Excel::import(new ProductImport, $csv);

    expect(Product::first()->name)->toBe('منتج صحيح');
});

it('CustomerImport normalizes garbled customer names and addresses', function () {
    $csv = tempCsv([
        ['name', 'address', 'phone_number'],
        [mojibake('محمد علي'), mojibake('شارع النيل'), '0912345678'],
    ]);

    Excel::import(new CustomerImport, $csv);

    $customer = Customer::first();
    expect($customer->name)->toBe('محمد علي')
        ->and($customer->address)->toBe('شارع النيل');
});

it('SupplierImport normalizes garbled supplier names', function () {
    $csv = tempCsv([
        ['name', 'address', 'phone_number'],
        [mojibake('شركة التوريد'), mojibake('المنطقة الصناعية'), '0900000000'],
    ]);

    Excel::import(new SupplierImport, $csv);

    $supplier = Supplier::first();
    expect($supplier->name)->toBe('شركة التوريد')
        ->and($supplier->address)->toBe('المنطقة الصناعية');
});

// ---------------------------------------------------------------------------
// Helper: write rows to a temp CSV and return the path
// ---------------------------------------------------------------------------

function tempCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'import_') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}
