<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * An immutable monetary amount stored in minor units (piasters/cents).
 *
 * The database stores every money column in minor units; the UI and the
 * application layer speak major units. This object owns the conversion
 * rule between the two, so the rounding behaviour lives in one place.
 */
final class Money implements JsonSerializable
{
    private function __construct(private readonly int $minor) {}

    public static function fromMajor(float|int|string $major): self
    {
        if (is_string($major) && ! is_numeric($major)) {
            throw new InvalidArgumentException("[{$major}] is not a numeric monetary amount.");
        }

        return new self((int) round((float) $major * 100));
    }

    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function major(): float
    {
        return $this->minor / 100;
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function negate(): self
    {
        return new self(-$this->minor);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function jsonSerialize(): float
    {
        return $this->major();
    }
}
