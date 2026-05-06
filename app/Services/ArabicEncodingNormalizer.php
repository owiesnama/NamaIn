<?php

namespace App\Services;

class ArabicEncodingNormalizer
{
    /**
     * Windows-1256 byte (0x80–0xFF) → Unicode code point.
     *
     * mbstring does not ship with Windows-1256 on all PHP builds, so we
     * maintain the mapping inline rather than relying on mb_convert_encoding.
     *
     * @var array<int, int>
     */
    public const WIN1256_MAP = [
        0x80 => 0x20AC, 0x81 => 0x067E, 0x82 => 0x201A, 0x83 => 0x0192,
        0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021,
        0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0679, 0x8B => 0x2039,
        0x8C => 0x0152, 0x8D => 0x0686, 0x8E => 0x0698, 0x8F => 0x0688,
        0x90 => 0x06AF, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
        0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
        0x98 => 0x06A9, 0x99 => 0x2122, 0x9A => 0x0691, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9D => 0x200C, 0x9E => 0x200D, 0x9F => 0x06BA,
        0xA0 => 0x00A0, 0xA1 => 0x060C, 0xA2 => 0x00A2, 0xA3 => 0x00A3,
        0xA4 => 0x00A4, 0xA5 => 0x00A5, 0xA6 => 0x00A6, 0xA7 => 0x00A7,
        0xA8 => 0x00A8, 0xA9 => 0x00A9, 0xAA => 0x06BE, 0xAB => 0x00AB,
        0xAC => 0x00AC, 0xAD => 0x00AD, 0xAE => 0x00AE, 0xAF => 0x00AF,
        0xB0 => 0x00B0, 0xB1 => 0x00B1, 0xB2 => 0x00B2, 0xB3 => 0x00B3,
        0xB4 => 0x00B4, 0xB5 => 0x00B5, 0xB6 => 0x00B6, 0xB7 => 0x00B7,
        0xB8 => 0x00B8, 0xB9 => 0x00B9, 0xBA => 0x061B, 0xBB => 0x00BB,
        0xBC => 0x00BC, 0xBD => 0x00BD, 0xBE => 0x00BE, 0xBF => 0x061F,
        0xC0 => 0x06C1, 0xC1 => 0x0621, 0xC2 => 0x0622, 0xC3 => 0x0623,
        0xC4 => 0x0624, 0xC5 => 0x0625, 0xC6 => 0x0626, 0xC7 => 0x0627,
        0xC8 => 0x0628, 0xC9 => 0x0629, 0xCA => 0x062A, 0xCB => 0x062B,
        0xCC => 0x062C, 0xCD => 0x062D, 0xCE => 0x062E, 0xCF => 0x062F,
        0xD0 => 0x0630, 0xD1 => 0x0631, 0xD2 => 0x0632, 0xD3 => 0x0633,
        0xD4 => 0x0634, 0xD5 => 0x0635, 0xD6 => 0x0636, 0xD7 => 0x00D7,
        0xD8 => 0x0637, 0xD9 => 0x0638, 0xDA => 0x0639, 0xDB => 0x063A,
        0xDC => 0x0640, 0xDD => 0x0641, 0xDE => 0x0642, 0xDF => 0x0643,
        0xE0 => 0x00E0, 0xE1 => 0x0644, 0xE2 => 0x00E2, 0xE3 => 0x0645,
        0xE4 => 0x0646, 0xE5 => 0x0647, 0xE6 => 0x0648, 0xE7 => 0x00E7,
        0xE8 => 0x00E8, 0xE9 => 0x0649, 0xEA => 0x064A, 0xEB => 0x00EB,
        0xEC => 0x064B, 0xED => 0x064C, 0xEE => 0x064D, 0xEF => 0x064E,
        0xF0 => 0x00F0, 0xF1 => 0x064F, 0xF2 => 0x0650, 0xF3 => 0x0651,
        0xF4 => 0x0652, 0xF5 => 0x0653, 0xF6 => 0x0654, 0xF7 => 0x00F7,
        0xF8 => 0x0655, 0xF9 => 0x00F9, 0xFA => 0x0670, 0xFB => 0x00FB,
        0xFC => 0x06D2, 0xFD => 0x00FD, 0xFE => 0x06CC, 0xFF => 0x06D2,
    ];

    /**
     * Normalize a string that may contain Windows-1256 mojibake back to correct UTF-8 Arabic.
     * If the text is already valid UTF-8 (Arabic or otherwise), it is returned unchanged.
     */
    public function normalize(string $text): string
    {
        if (! $this->isGarbled($text)) {
            return $text;
        }

        return $this->fix($text);
    }

    /**
     * Detect if text contains Windows-1256 mojibake.
     *
     * Mojibake pattern: Latin-1 supplement chars (U+00C0–U+00FF) with no valid Arabic present.
     */
    public function isGarbled(string $text): bool
    {
        // Already contains valid Arabic — nothing to fix
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return false;
        }

        // Contains Latin-1 supplement chars typical of Windows-1256 mojibake
        return (bool) preg_match('/[\xC0-\xFF]/u', $text);
    }

    /**
     * Produce Windows-1256 mojibake from a UTF-8 Arabic string.
     * Used in tests to generate fixture data without requiring Windows-1256 in mbstring.
     */
    public static function createMojibake(string $arabic): string
    {
        /** @var array<int, int> $reverseMap Unicode codepoint → Windows-1256 byte */
        static $reverseMap = null;
        if ($reverseMap === null) {
            $reverseMap = array_flip(self::WIN1256_MAP);
        }

        $result = '';

        foreach (mb_str_split($arabic, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');

            if ($cp < 0x80) {
                $result .= $char;
            } elseif (isset($reverseMap[$cp])) {
                // Convert the Windows-1256 byte to the UTF-8 encoding of that ISO-8859-1 character
                $result .= mb_convert_encoding(chr($reverseMap[$cp]), 'UTF-8', 'ISO-8859-1');
            }
        }

        return $result;
    }

    private function fix(string $text): string
    {
        // The mojibake was created by treating Windows-1256 bytes as ISO-8859-1,
        // then storing as UTF-8. Recover the original bytes via ISO-8859-1 decode.
        $bytes = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        // Re-decode each byte using the Windows-1256 character map
        $result = '';
        $len = strlen($bytes);

        for ($i = 0; $i < $len; $i++) {
            $byte = ord($bytes[$i]);

            if ($byte < 0x80) {
                $result .= chr($byte);
            } elseif (isset(self::WIN1256_MAP[$byte])) {
                $result .= mb_chr(self::WIN1256_MAP[$byte], 'UTF-8');
            } else {
                $result .= chr($byte);
            }
        }

        if ($this->isValidArabic($result)) {
            return $result;
        }

        // Fallback: try ISO-8859-6 (older Linux Arabic systems)
        $fallback = mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-6');

        return $this->isValidArabic($fallback) ? $fallback : $text;
    }

    private function isValidArabic(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }
}
