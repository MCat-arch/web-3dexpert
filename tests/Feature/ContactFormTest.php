<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforces D12: contact is a direct WhatsApp link, never a form.
 *
 * This is a guard, not a leftover. Deleting it would remove the only thing
 * stopping a contact route from reappearing -- and the dead ContactRequest /
 * ContactSubmission artifacts it used to accompany are removed separately.
 */
class ContactFormTest extends TestCase
{
    // The 404 page renders the layout, which reads site_settings -- without a
    // schema these assertions fail as 500s rather than the 404s under test.
    use RefreshDatabase;

    public function test_it_does_not_expose_a_standalone_contact_form_or_submission_endpoint(): void
    {
        $this->get('/kontak')->assertNotFound();
        $this->get('/contact')->assertNotFound();
        $this->post('/kontak', [])->assertNotFound();
        $this->post('/contact', [])->assertNotFound();
    }
}
