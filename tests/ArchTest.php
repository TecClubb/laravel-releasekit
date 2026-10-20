<?php

use Illuminate\Database\Eloquent\Model;
use TecClub\ReleaseKit\Exceptions\ReleaseKitException;
use TecClub\ReleaseKit\Support\VersionComparator;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('versions are only compared through VersionComparator')
    ->expect('version_compare')
    ->toOnlyBeUsedIn(VersionComparator::class);

arch('enums are string backed')
    ->expect('TecClub\ReleaseKit\Enums')
    ->toBeStringBackedEnums();

arch('exceptions extend the package base exception')
    ->expect('TecClub\ReleaseKit\Exceptions')
    ->toExtend(ReleaseKitException::class)
    ->ignoring(ReleaseKitException::class);

arch('models extend Eloquent')
    ->expect('TecClub\ReleaseKit\Models')
    ->toExtend(Model::class);
