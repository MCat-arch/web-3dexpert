<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for a bug that shipped in P5a.
 *
 * Blade's @can resolves the DEFAULT guard. That is only `customer` on routes
 * behind auth:customer, because Laravel's Authenticate middleware calls
 * shouldUse() when it succeeds. On a PUBLIC route the default stays `web`, so
 *
 * @can sees no user and every ability silently returns false -- the owner's
 * cancel button simply never rendered on the public event detail page.
 *
 * What made it survive review is the more useful lesson: the obvious test
 * passes. actingAs($customer, 'customer') ALSO calls shouldUse(), so it makes
 * the default guard `customer` for the whole request and hides the difference
 * between the test and a real browser.
 *
 * These tests therefore authenticate with auth('customer')->login() and never
 * actingAs, which is what a real session does.
 */
class CustomerBladeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cancel_control_renders_on_the_public_event_page(): void
    {
        $owner = Customer::factory()->create();
        $event = Event::factory()->capacity(10)->create();
        EventRegistration::factory()->for($event)->for($owner)->create();

        auth('customer')->login($owner);

        $this->get('/acara/'.$event->slug)->assertSee(__('events.cancel'));
    }

    public function test_the_cancel_control_is_absent_for_a_non_owner_on_the_public_page(): void
    {
        $owner = Customer::factory()->create();
        $stranger = Customer::factory()->create();
        $event = Event::factory()->capacity(10)->create();
        EventRegistration::factory()->for($event)->for($owner)->create();

        auth('customer')->login($stranger);

        $this->get('/acara/'.$event->slug)->assertDontSee(__('events.cancel'));
    }

    public function test_a_guest_sees_no_cancel_control(): void
    {
        $owner = Customer::factory()->create();
        $event = Event::factory()->capacity(10)->create();
        EventRegistration::factory()->for($event)->for($owner)->create();

        $this->get('/acara/'.$event->slug)->assertDontSee(__('events.cancel'));
    }

    public function test_the_withdraw_control_renders_for_the_owning_applicant(): void
    {
        $owner = Customer::factory()->create();
        $application = JobApplication::factory()
            ->for(JobVacancy::factory()->create(['is_published' => true]), 'vacancy')
            ->for($owner)
            ->create();

        auth('customer')->login($owner);

        $this->get('/akun/lamaran')->assertSee(__('careers.withdraw'));
        $this->assertTrue($application->isActive());
    }

    public function test_the_withdraw_control_is_absent_once_withdrawn(): void
    {
        $owner = Customer::factory()->create();
        JobApplication::factory()
            ->withdrawn()
            ->for(JobVacancy::factory()->create(['is_published' => true]), 'vacancy')
            ->for($owner)
            ->create();

        auth('customer')->login($owner);

        $this->get('/akun/lamaran')->assertDontSee(__('careers.withdraw'));
    }
}
