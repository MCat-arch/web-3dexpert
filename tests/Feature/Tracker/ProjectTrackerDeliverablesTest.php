<?php

namespace Tests\Feature\Tracker;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The visible half of the tracker: progress, attachments, and the finished
 * deliverables. The security contract is covered separately in
 * ProgressTrackerNegativeResponseTest.
 */
class ProjectTrackerDeliverablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProjectAttachment::DISK);
    }

    private function lookup(Project $project, string $verification): TestResponse
    {
        return $this->post('/lacak-proyek', [
            'project_code' => $project->project_code,
            'verification' => $verification,
        ])->assertOk();
    }

    private function attachment(Project $project, string $kind, string $name, ?ProjectUpdate $update = null): ProjectAttachment
    {
        $path = 'projects/test/'.$name;

        Storage::disk(ProjectAttachment::DISK)->put($path, 'file-'.$name);

        return $project->attachments()->create([
            'project_update_id' => $update?->getKey(),
            'kind' => $kind,
            'title' => 'Berkas '.$name,
            'path' => $path,
            'original_name' => $name,
        ]);
    }

    public function test_a_completed_project_shows_its_summary_and_final_files_to_the_owner(): void
    {
        $project = Project::factory()->completed()->create([
            'customer_phone' => '081234567890',
            'summary' => 'Semua berkas sudah diserahkan melalui halaman ini.',
        ]);
        $project->forceFill(['progress_percent' => 40])->saveQuietly();

        $this->attachment($project, ProjectAttachment::KIND_DELIVERABLE, 'gambar-kerja.pdf');

        $this->lookup($project, '081234567890')
            ->assertSee(__('tracker.final_heading'))
            ->assertSee('Semua berkas sudah diserahkan melalui halaman ini.')
            ->assertSee('Berkas gambar-kerja.pdf')
            ->assertSee('100%');
    }

    public function test_an_unfinished_project_hides_the_final_section_and_its_deliverables(): void
    {
        $project = Project::factory()->create([
            'customer_phone' => '081234567890',
            'progress_percent' => 55,
            'summary' => 'Catatan penutup yang belum boleh tampil.',
        ]);

        $this->attachment($project, ProjectAttachment::KIND_DELIVERABLE, 'belum-final.pdf');

        $this->lookup($project, '081234567890')
            ->assertSee('55%')
            ->assertDontSee(__('tracker.final_heading'))
            ->assertDontSee('Catatan penutup yang belum boleh tampil.')
            ->assertDontSee('Berkas belum-final.pdf');
    }

    public function test_progress_attachments_are_listed_while_the_project_runs(): void
    {
        $project = Project::factory()->create([
            'customer_phone' => '081234567890',
            'progress_percent' => 30,
        ]);

        $this->attachment($project, ProjectAttachment::KIND_PROGRESS, 'render-terbaru.png');

        $this->lookup($project, '081234567890')
            ->assertSee(__('tracker.attachments'))
            ->assertSee('Berkas render-terbaru.png')
            ->assertSee(__('tracker.download'));
    }

    public function test_a_signed_link_streams_the_file_and_a_tampered_one_is_rejected(): void
    {
        $project = Project::factory()->create(['customer_phone' => '081234567890']);
        $attachment = $this->attachment($project, ProjectAttachment::KIND_PROGRESS, 'progres.pdf');

        $url = URL::temporarySignedRoute(
            'tracker.attachments.show',
            now()->addMinutes(30),
            ['attachment' => $attachment->getKey()],
        );

        $this->get($url)->assertOk();

        $this->get($url.'&extra=1')->assertForbidden();
    }

    public function test_a_deliverable_cannot_be_downloaded_before_the_project_is_completed(): void
    {
        $project = Project::factory()->create(['customer_phone' => '081234567890']);
        $attachment = $this->attachment($project, ProjectAttachment::KIND_DELIVERABLE, 'final.pdf');

        $url = URL::temporarySignedRoute(
            'tracker.attachments.show',
            now()->addMinutes(30),
            ['attachment' => $attachment->getKey()],
        );

        $this->get($url)->assertNotFound();
    }

    public function test_a_cancelled_project_stops_serving_files_even_with_a_valid_signature(): void
    {
        $project = Project::factory()->create(['customer_phone' => '081234567890']);
        $attachment = $this->attachment($project, ProjectAttachment::KIND_PROGRESS, 'progres.png');

        $url = URL::temporarySignedRoute(
            'tracker.attachments.show',
            now()->addMinutes(30),
            ['attachment' => $attachment->getKey()],
        );

        $project->forceFill(['status' => Project::STATUS_CANCELLED])->save();

        $this->get($url)->assertNotFound();
    }

    public function test_completing_a_project_forces_the_bar_to_100_and_stamps_the_date(): void
    {
        $project = Project::factory()->create([
            'progress_percent' => 60,
            'completed_at' => null,
        ]);

        $project->forceFill(['status' => Project::STATUS_COMPLETED])->save();

        $project->refresh();

        $this->assertSame(100, $project->progress_percent);
        $this->assertSame(100, $project->progressPercent());
        $this->assertNotNull($project->completed_at);
    }

    public function test_progress_is_clamped_to_a_readable_range(): void
    {
        $project = Project::factory()->create(['progress_percent' => 0]);
        $project->forceFill(['progress_percent' => 140])->saveQuietly();

        $this->assertSame(100, $project->progressPercent());

        $project->forceFill(['progress_percent' => 0])->saveQuietly();
        $this->assertSame(0, $project->progressPercent());
    }
}
