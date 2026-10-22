<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may make an outbound network request. The app no longer calls
        // a translation API, but keeping this guard catches accidental HTTP
        // calls introduced later and keeps the suite deterministic.
        Http::preventStrayRequests();
    }
}
