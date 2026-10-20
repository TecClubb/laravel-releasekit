<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TecClub\ReleaseKit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in('Feature');
