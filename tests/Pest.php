<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Tests run against MySQL/MariaDB (see phpunit.xml), the same kind of database as production, so
 * constraints and locking behave as they will live.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Concurrency tests fork, so their data must really be committed: truncation instead of the
 * transaction RefreshDatabase wraps each test in.
 */
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

pest()->extend(TestCase::class)->in('Unit');
