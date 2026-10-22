<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * MERGE NOTE: `composer create-project` generates its own tests/Pest.php.
 * Keep whichever TestCase path the generated file uses -- it has varied across
 * framework versions -- and add the RefreshDatabase trait binding below.
 */

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');
