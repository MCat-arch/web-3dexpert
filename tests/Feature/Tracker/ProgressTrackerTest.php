<?php

namespace Tests\Feature\Tracker;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgressTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_renders_in_both_locales(): void
    {
        $this->get('/lacak-proyek')->assertOk()->assertSee('lang="id"', false);
        $this->get('/en/lacak-proyek')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_no_account_is_required(): void
    {
        // A6: the tracker works without signing in. A customer who never
        // registered still has a project.
        $project = Project::factory()->create(['customer_phone' => '081234567890']);

        $this->assertGuest('customer');

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()->assertSee($project->title);
    }

    public function test_a_matching_phone_returns_the_project(): void
    {
        $project = Project::factory()->create([
            'customer_phone' => '081234567890',
            'title' => 'Desain Rumah Tropis',
            'current_stage' => 'Gambar Kerja',
        ]);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()
            ->assertSee('Desain Rumah Tropis')
            ->assertSee('Gambar Kerja')
            ->assertSee(__('tracker.status_in_progress'));
    }

    public function test_a_matching_email_returns_the_project(): void
    {
        // Either factor works -- the customer should not have to remember
        // which one we hold.
        $project = Project::factory()->create(['customer_email' => 'budi@example.test']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => 'budi@example.test',
        ])->assertOk()->assertSee($project->title);
    }

    public function test_the_email_match_is_case_insensitive(): void
    {
        $project = Project::factory()->create(['customer_email' => 'budi@example.test']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '  BUDI@Example.TEST ',
        ])->assertOk()->assertSee($project->title);
    }

    /** @return array<string, array{string}> */
    public static function phoneFormats(): array
    {
        return [
            'national' => ['081234567890'],
            'national with dashes' => ['0812-3456-7890'],
            'international' => ['+62 812-3456-7890'],
            'international without plus' => ['62 812 3456 7890'],
        ];
    }

    #[DataProvider('phoneFormats')]
    public function test_the_phone_match_ignores_formatting(string $typed): void
    {
        // Shares App\Support\PhoneNumber with P1's registration, so a number
        // stored at signup and a number typed here always agree. Without it a
        // real customer would be told their own code is invalid.
        $project = Project::factory()->create(['customer_phone' => '081234567890']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => $typed,
        ])->assertOk()->assertSee($project->title);
    }

    /** @return array<string, array{string}> */
    public static function codeFormats(): array
    {
        return [
            'exact' => ['%s'],
            'lower case' => ['%s'],
            'with spaces' => [' %s '],
        ];
    }

    public function test_the_code_match_tolerates_case_spaces_and_a_missing_prefix(): void
    {
        // Being generous on formatting costs nothing: the second factor is
        // what provides the security, not the code's punctuation.
        $project = Project::factory()->create(['customer_phone' => '081234567890']);
        $code = $project->project_code;
        $bare = str_replace('3DX-', '', $code);

        foreach ([$code, strtolower($code), ' '.$code.' ', $bare, strtolower($bare)] as $variant) {
            $this->post('/lacak-proyek', [
                'project_code' => $variant,
                'verification' => '081234567890',
            ])->assertOk()->assertSee($project->title, false);

            $this->flushSession();
        }
    }

    public function test_an_on_hold_project_is_still_trackable(): void
    {
        // "Not done yet" includes on-hold: the customer is precisely the
        // person who should be able to see that it has stalled.
        $project = Project::factory()->onHold()->create(['customer_phone' => '081234567890']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()
            ->assertSee($project->title)
            ->assertSee(__('tracker.status_on_hold'));
    }

    public function test_updates_render_in_order_with_their_notes(): void
    {
        // current_stage is set to something that cannot collide: the factory
        // default is 'Desain 3D', and the stage field renders ABOVE the
        // timeline -- an earlier draft of this test matched that instead of
        // the update and failed for the wrong reason.
        $project = Project::factory()->create([
            'customer_phone' => '081234567890',
            'current_stage' => 'Tahap Berjalan',
        ]);

        ProjectUpdate::factory()->for($project)->create([
            'stage_name' => 'TahapPertamaUnik', 'sort_order' => 1, 'note' => 'Sudah diukur.',
        ]);
        ProjectUpdate::factory()->for($project)->create([
            'stage_name' => 'TahapKeduaUnik', 'sort_order' => 2, 'note' => 'Revisi kedua.',
        ]);

        $body = $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()->getContent();

        $this->assertLessThan(
            strpos($body, 'TahapKeduaUnik'),
            strpos($body, 'TahapPertamaUnik'),
            'Updates must render in sort order.',
        );
        $this->assertStringContainsString('Sudah diukur.', $body);
        $this->assertStringContainsString('Revisi kedua.', $body);
    }

    public function test_a_project_with_no_updates_still_renders(): void
    {
        $project = Project::factory()->create(['customer_phone' => '081234567890']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()->assertSee(__('tracker.no_updates'));
    }

    public function test_a_project_without_a_stored_email_cannot_be_matched_by_a_blank_one(): void
    {
        // Guards a null-vs-null comparison granting access to a stranger.
        $project = Project::factory()->withoutEmail()->create(['customer_phone' => '081234567890']);

        // A blank field fails `required` and redirects back, which is the
        // right behaviour -- it never reaches the lookup at all.
        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => ' ',
        ])->assertRedirect()->assertSessionHasErrors('verification');
    }

    public function test_a_project_without_a_stored_phone_cannot_be_matched_by_any_phone(): void
    {
        $project = Project::factory()->withoutPhone()->create(['customer_email' => 'budi@example.test']);

        $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => '081234567890',
        ])->assertOk()->assertDontSee($project->title);
    }

    public function test_both_fields_are_required(): void
    {
        $this->post('/lacak-proyek', [])->assertSessionHasErrors(['project_code', 'verification']);
    }

    public function test_the_lookup_requires_a_post(): void
    {
        // The submitted phone or email is personal data: over GET it would
        // land in browser history, access logs and Referer headers.
        $project = Project::factory()->create();

        $this->get('/lacak-proyek?project_code='.$project->project_code.'&verification=081234567890')
            ->assertOk()
            ->assertDontSee($project->title);
    }
}
