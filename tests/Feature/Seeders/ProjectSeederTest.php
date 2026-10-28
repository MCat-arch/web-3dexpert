<?php

namespace Tests\Feature\Seeders;

use App\Models\Project;
use App\Models\ProjectAttachment;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demo seeder is run by hand in production, so it must be safe to run
 * twice: never overwrite a real project that happens to share a code, and
 * never duplicate rows or files.
 */
class ProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProjectAttachment::DISK);
    }

    public function test_it_creates_both_demo_projects_with_real_files(): void
    {
        $this->seed(ProjectSeeder::class);

        $this->assertSame(2, Project::count());

        $inProgress = Project::where('project_code', '3DX-DEMO7A2K')->sole();
        $completed = Project::where('project_code', '3DX-DEMO8C4M')->sole();

        $this->assertSame(55, $inProgress->progressPercent());
        $this->assertTrue($inProgress->isTrackable());
        $this->assertGreaterThanOrEqual(3, $inProgress->attachments()->count());

        $this->assertSame(100, $completed->progressPercent());
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(3, $completed->deliverables()->count());

        foreach ($completed->deliverables as $deliverable) {
            Storage::disk(ProjectAttachment::DISK)->assertExists($deliverable->path);
            $this->assertGreaterThan(0, $deliverable->size_kb);
        }
    }

    public function test_the_tracker_renders_the_demo_projects(): void
    {
        $this->seed(ProjectSeeder::class);

        $this->post('/lacak-proyek', [
            'project_code' => '3DX-DEMO7A2K',
            'verification' => '081234567890',
        ])
            ->assertOk()
            ->assertSee('Desain Interior Rumah Tinggal Bapak Budi')
            ->assertSee('55%');

        $this->post('/lacak-proyek', [
            'project_code' => '3DX-DEMO8C4M',
            'verification' => 'sari.wulandari@example.test',
        ])
            ->assertOk()
            ->assertSee(__('tracker.final_heading'))
            ->assertSee('Paket gambar kerja lengkap (ZIP)');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(ProjectSeeder::class);
        $this->seed(ProjectSeeder::class);

        $this->assertSame(2, Project::count());
        $this->assertSame(6, ProjectAttachment::count());

        $fileCount = count(Storage::disk(ProjectAttachment::DISK)->allFiles());
        $this->seed(ProjectSeeder::class);

        $this->assertSame(6, ProjectAttachment::count());
        $this->assertSame($fileCount, count(Storage::disk(ProjectAttachment::DISK)->allFiles()));
    }
}
