<?php

namespace App\Imports\Concerns;

use App\Services\ArabicEncodingNormalizer;

trait NormalizesArabicEncoding
{
    protected function normalizeText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return app(ArabicEncodingNormalizer::class)->normalize($value);
    }
}
