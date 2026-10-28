<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrackSiteTrafficTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_page_view_is_recorded_without_storing_the_ip(): void
    {
        $this->get('/')->assertOk();

        $row = DB::table('site_traffic')->first();

        $this->assertNotNull($row);
        $this->assertSame('/', $row->path);
        $this->assertSame('GET', $row->method);
        $this->assertSame(200, (int) $row->status_code);
        $this->assertFalse((bool) $row->is_bot);
        $this->assertSame(64, strlen((string) $row->visitor_hash));

        $this->assertNotContains('ip_address', Schema::getColumnListing('site_traffic'));
    }

    public function test_the_admin_panel_is_never_counted_as_traffic(): void
    {
        $this->get('/admin/login')->assertOk();

        $this->assertDatabaseCount('site_traffic', 0);
    }

    public function test_bots_are_flagged_instead_of_polluting_human_traffic(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/')->assertOk();

        $row = DB::table('site_traffic')->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_bot);
    }

    public function test_purge_removes_only_rows_past_the_retention_window(): void
    {
        DB::table('site_traffic')->insert([
            ['path' => '/', 'method' => 'GET', 'status_code' => 200, 'created_at' => now()->subDays(90)],
            ['path' => '/', 'method' => 'GET', 'status_code' => 200, 'created_at' => now()->subDays(2)],
        ]);

        $this->artisan('traffic:purge')->assertSuccessful();

        $this->assertDatabaseCount('site_traffic', 1);
        $this->assertDatabaseHas('site_traffic', ['path' => '/']);
    }
}
