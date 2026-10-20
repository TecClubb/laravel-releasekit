<?php

namespace TecClub\ReleaseKit\Support;

use TecClub\ReleaseKit\Exceptions\InvalidVersion;

/**
 * The single place where versions are validated and compared.
 *
 * Versions have 2 to 4 numeric parts ("1.2", "1.2.0", "1.2.0.4"). Missing
 * trailing parts count as zero, so "1.2" equals "1.2.0".
 */
final class VersionComparator
{
    public const PATTERN = '/^\d+(\.\d+){1,3}$/D';

    private const PARTS = 4;

    public static function isValid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    /**
     * @throws InvalidVersion
     */
    public static function assertValid(string $version): void
    {
        if (! self::isValid($version)) {
            throw InvalidVersion::format($version);
        }
    }

    /**
     * Pads to four parts and strips leading zeros: "01.2" becomes "1.2.0.0".
     *
     * @throws InvalidVersion
     */
    public static function normalize(string $version): string
    {
        self::assertValid($version);

        $parts = array_map(
            fn (string $part): string => ltrim($part, '0') ?: '0',
            explode('.', $version),
        );

        return implode('.', array_pad($parts, self::PARTS, '0'));
    }

    /**
     * Returns -1, 0 or 1.
     *
     * @throws InvalidVersion
     */
    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    public static function equals(string $a, string $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    public static function lessThan(string $a, string $b): bool
    {
        return self::compare($a, $b) < 0;
    }

    public static function lessThanOrEqual(string $a, string $b): bool
    {
        return self::compare($a, $b) <= 0;
    }

    public static function greaterThan(string $a, string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function greaterThanOrEqual(string $a, string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }
}
