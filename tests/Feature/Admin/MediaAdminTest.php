<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\HeroSlide;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaAdminTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $path): MediaAsset
    {
        return MediaAsset::query()->create([
            'path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'sha256' => hash('sha256', $path),
            'storage_path' => $path,
            'content' => 'binary payload that must not be selected',
        ]);
    }

    public function test_the_listing_query_does_not_select_the_content_blob(): void
    {
        $asset = $this->asset('images/unreferenced.jpg');

        $attributes = MediaAssetResource::getEloquentQuery()
            ->whereKey($asset->id)
            ->firstOrFail()
            ->getAttributes();

        $this->assertArrayNotHasKey('content', $attributes);
        $this->assertSame('images/unreferenced.jpg', $attributes['path']);
    }

    public function test_a_referenced_asset_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());

        $asset = $this->asset('images/used.jpg');
        HeroSlide::factory()->create(['image_path' => $asset->path]);

        $this->assertFalse(MediaAssetResource::canDelete($asset));
    }

    public function test_superadmin_can_delete_an_unreferenced_asset(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());

        $asset = $this->asset('images/unreferenced.jpg');

        $this->assertTrue(MediaAssetResource::canDelete($asset));
    }

    public function test_editor_and_lms_admin_cannot_delete_media(): void
    {
        $asset = $this->asset('images/used.jpg');

        $this->actingAs(User::factory()->editor()->create());
        $this->assertTrue(MediaAssetResource::canViewAny());
        $this->assertFalse(MediaAssetResource::canDelete($asset));

        $this->actingAs(User::factory()->lmsAdmin()->create());
        $this->assertTrue(MediaAssetResource::canViewAny());
        $this->assertFalse(MediaAssetResource::canDelete($asset));
    }
}
