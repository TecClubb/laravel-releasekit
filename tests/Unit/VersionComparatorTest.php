<?php

use TecClub\ReleaseKit\Exceptions\InvalidVersion;
use TecClub\ReleaseKit\Exceptions\ReleaseKitException;
use TecClub\ReleaseKit\Support\VersionComparator;

it('accepts 2 to 4 numeric parts', function (string $version) {
    expect(VersionComparator::isValid($version))->toBeTrue();
})->with(['1.2', '1.2.0', '1.2.0.4', '0.1', '10.20.30', '2026.10.20.1']);

it('rejects invalid formats', function (string $version) {
    expect(VersionComparator::isValid($version))->toBeFalse();
})->with([
    'single part' => '1',
    'five parts' => '1.2.3.4.5',
    'pre-release suffix' => '1.2.0-beta',
    'build metadata' => '1.2.0+45',
    'v prefix' => 'v1.2.0',
    'trailing dot' => '1.2.',
    'leading dot' => '.1.2',
    'empty part' => '1..2',
    'whitespace' => ' 1.2.0',
    'trailing newline' => "1.2.0\n",
    'empty' => '',
    'letters' => 'a.b',
]);

it('throws a ReleaseKitException for invalid versions', function () {
    VersionComparator::compare('1.2.0-beta', '1.2.0');
})->throws(InvalidVersion::class);

it('uses the package exception hierarchy', function () {
    expect(new InvalidVersion('x'))->toBeInstanceOf(ReleaseKitException::class);
});

it('compares numerically, not lexically', function () {
    expect(VersionComparator::greaterThan('1.10.0', '1.9.0'))->toBeTrue()
        ->and(VersionComparator::lessThan('1.9.0', '1.10.0'))->toBeTrue()
        ->and(VersionComparator::compare('2.0', '10.0'))->toBe(-1);
});

it('treats missing trailing parts as zero', function () {
    expect(VersionComparator::equals('1.2', '1.2.0'))->toBeTrue()
        ->and(VersionComparator::equals('1.2', '1.2.0.0'))->toBeTrue()
        ->and(VersionComparator::compare('1.2.0', '1.2'))->toBe(0);
});

it('compares four-part versions', function () {
    expect(VersionComparator::greaterThan('1.2.0.4', '1.2.0.3'))->toBeTrue()
        ->and(VersionComparator::greaterThan('1.2.0.1', '1.2'))->toBeTrue()
        ->and(VersionComparator::lessThan('1.2.0.9', '1.2.1'))->toBeTrue();
});

it('ignores leading zeros', function () {
    expect(VersionComparator::equals('01.02.003', '1.2.3'))->toBeTrue()
        ->and(VersionComparator::normalize('01.0.00'))->toBe('1.0.0.0');
});

it('exposes all comparison helpers', function () {
    expect(VersionComparator::lessThanOrEqual('1.2', '1.2.0'))->toBeTrue()
        ->and(VersionComparator::lessThanOrEqual('1.1', '1.2'))->toBeTrue()
        ->and(VersionComparator::lessThanOrEqual('1.3', '1.2'))->toBeFalse()
        ->and(VersionComparator::greaterThanOrEqual('1.2.0', '1.2'))->toBeTrue()
        ->and(VersionComparator::greaterThanOrEqual('1.1', '1.2'))->toBeFalse();
});
