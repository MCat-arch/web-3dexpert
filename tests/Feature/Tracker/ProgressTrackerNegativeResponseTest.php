<?php

namespace Tests\Feature\Tracker;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The security contract of P7 (decision D2).
 *
 * The tracker is unauthenticated and reads customer data, so the only thing
 * standing between an attacker and every customer's project is that a failure
 * tells them NOTHING -- not whether the code exists, not whether the contact
 * was close, not whether the project finished.
 *
 * These tests compare whole response bodies, not messages. A distinguishable
 * byte anywhere is a distinguishable answer: it is enough to confirm a code is
 * real, at which point the second factor stops being a factor at all.
 *
 * The inputs are held IDENTICAL across each comparison and only the database
 * state changes -- otherwise the echoed form values would differ and the
 * comparison would prove nothing.
 */
class ProgressTrackerNegativeResponseTest extends TestCase
{
    use RefreshDatabase;

    private function lookup(string $code, string $verification): string
    {
        $body = $this->post('/lacak-proyek', [
            'project_code' => $code,
            'verification' => $verification,
        ])->assertOk()->getContent();

        return $this->stripFrameworkNoise((string) $body);
    }

    /**
     * Removes Livewire's auto-injected asset block before comparing.
     *
     * Livewire injects its styles into the FIRST HTML response of an
     * application instance and skips them afterwards, so two requests inside
     * one test differ by that block even when the application behaved
     * identically. Comparing raw bodies made these tests pass alone and fail
     * in the full suite, which is the wrong way round for a security
     * assertion.
     *
     * Stripping it is safe and does not weaken anything: the block is a static
     * CSS blob, byte-identical for every visitor, and cannot carry project
     * data. Everything else -- markup, copy, status code, every echoed value --
     * is still compared byte for byte.
     */
    private function stripFrameworkNoise(string $body): string
    {
        // Both blocks: Livewire injects Styles into <head> and Scripts before
        // </body>, each exactly once per application instance.
        $body = (string) preg_replace(
            [
                '/<!-- Livewire Styles -->.*?<\/style>/s',
                '/<!-- Livewire Scripts -->.*?<\/script>/s',
                // Current Livewire versions inject a plain <script> tag with
                // no marker comment, and inject it only on the first response
                // of some application instances. Strip by URL as well.
                '/<script[^>]*\bsrc="[^"]*livewire[^"]*"[^>]*><\/script>/is',
            ],
            '',
            $body,
        );

        // Collapse whitespace too: removing the block above leaves a stray
        // newline where it used to sit. Whitespace cannot carry project data
        // any more than the CSS could, and every piece of actual content is
        // still compared exactly.
        return trim((string) preg_replace('/\s+/', ' ', $body));
    }

    public function test_a_wrong_contact_is_indistinguishable_from_an_unknown_code(): void
    {
        $project = Project::factory()->create([
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@example.test',
        ]);
        $code = $project->project_code;

        // Right code, wrong contact.
        $wrongContact = $this->lookup($code, '080000000000');

        // Same input, but now the project does not exist at all.
        $project->forceDelete();
        $unknownCode = $this->lookup($code, '080000000000');

        $this->assertSame(
            $unknownCode,
            $wrongContact,
            'A real code with the wrong contact must look exactly like a code that does not exist.',
        );
    }

    public function test_a_completed_project_is_indistinguishable_from_an_unknown_code(): void
    {
        // The brief said "only the project that not done yet". Implemented as
        // a uniform negative: saying "already finished" would confirm both the
        // code AND the contact were correct.
        $project = Project::factory()->completed()->create([
            'customer_phone' => '081234567890',
        ]);
        $code = $project->project_code;

        $completed = $this->lookup($code, '081234567890');

        $project->forceDelete();
        $unknownCode = $this->lookup($code, '081234567890');

        $this->assertSame($unknownCode, $completed);
    }

    public function test_a_cancelled_project_is_indistinguishable_from_an_unknown_code(): void
    {
        // Decision K10: cancelled is hidden like completed. A cancellation is
        // a conversation for a person, not an announcement from a form.
        $project = Project::factory()->cancelled()->create([
            'customer_phone' => '081234567890',
        ]);
        $code = $project->project_code;

        $cancelled = $this->lookup($code, '081234567890');

        $project->forceDelete();
        $unknownCode = $this->lookup($code, '081234567890');

        $this->assertSame($unknownCode, $cancelled);
    }

    public function test_a_hidden_project_is_indistinguishable_from_an_unknown_code(): void
    {
        $project = Project::factory()->hidden()->create(['customer_phone' => '081234567890']);
        $code = $project->project_code;

        $hidden = $this->lookup($code, '081234567890');

        $project->forceDelete();
        $unknownCode = $this->lookup($code, '081234567890');

        $this->assertSame($unknownCode, $hidden);
    }

    public function test_no_negative_response_leaks_any_project_detail(): void
    {
        $project = Project::factory()->completed()->create([
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@example.test',
            'title' => 'Desain Rumah Rahasia',
            'current_stage' => 'Tahap Rahasia',
        ]);

        ProjectUpdate::factory()->for($project)->create(['note' => 'Catatan rahasia']);

        $body = $this->lookup($project->project_code, '081234567890');

        foreach (['Budi Santoso', 'Desain Rumah Rahasia', 'Tahap Rahasia', 'Catatan rahasia', 'budi@example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_the_failure_message_is_the_same_single_string(): void
    {
        // Pins the one message. If someone later adds a second, more helpful
        // one, the tests above fail -- but this says why in one line.
        $project = Project::factory()->create(['customer_phone' => '081234567890']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => 'salah',
        ])->assertOk()->assertSee(__('tracker.not_found'));

        $this->post('/lacak-proyek', [
            'project_code' => '3DX-TIDAKADA',
            'verification' => 'salah',
        ])->assertOk()->assertSee(__('tracker.not_found'));
    }

    public function test_validation_never_reports_whether_a_code_exists(): void
    {
        // An `exists:` rule here would be the same oracle by another route:
        // the field error would confirm which codes are real.
        $this->post('/lacak-proyek', [
            'project_code' => '3DX-TIDAKADA',
            'verification' => '081234567890',
        ])->assertOk()->assertSessionHasNoErrors();
    }
}
