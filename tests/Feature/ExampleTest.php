<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // The homepage reads site_settings and content tables, so it needs a
    // schema. This was shipped with the trait commented out, which meant the
    // test could only ever have failed once the suite was able to run.
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk();
    }
}
