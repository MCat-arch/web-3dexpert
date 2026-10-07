<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The legacy site wrote uploads straight into a web-served directory with no
 * extension or MIME check, so an uploaded .php executed. These tests assert the
 * replacement cannot do that.
 */
class MediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_media_bytes_on_the_private_disk_instead_of_in_the_database(): void
    {
        Storage::fake('media');

        $source = tempnam(sys_get_temp_dir(), 'media-asset-');
        file_put_contents($source, 'private image bytes');

        try {
            $asset = MediaAsset::storeFile('portfolio/photo.jpg', $source, 'photo.jpg');
        } finally {
            unlink($source);
        }

        Storage::disk('media')->assertExists('portfolio/photo.jpg');
        $this->assertSame('portfolio/photo.jpg', $asset->storage_path);
        $this->assertSame('', $asset->content);
        $this->assertSame(strlen('private image bytes'), (int) $asset->size);
    }

    public function test_it_serves_a_stored_image_with_the_content_type_this_app_chooses(): void
    {
        MediaAsset::create([
            'path' => 'portfolio/photo.jpg',
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 4,
            'sha256' => hash('sha256', 'image'),
            'content' => 'image',
        ]);

        $response = $this->get(route('media.show', ['path' => 'portfolio/photo.jpg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // MediaController returns a StreamedResponse, so getContent() -- which
        // is what assertSee reads -- is empty until the stream is sent.
        $this->assertSame('image', $response->streamedContent());
    }

    public function test_it_streams_media_from_the_private_disk(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('portfolio/photo.jpg', 'image');

        MediaAsset::create([
            'path' => 'portfolio/photo.jpg',
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 5,
            'sha256' => hash('sha256', 'image'),
            'storage_path' => 'portfolio/photo.jpg',
            'content' => '',
        ]);

        $response = $this->get(route('media.show', ['path' => 'portfolio/photo.jpg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->assertSame('image', $response->streamedContent());
    }

    public function test_it_refuses_to_serve_a_php_file_even_when_one_is_stored(): void
    {
        MediaAsset::create([
            'path' => 'portfolio/evil.php',
            'file_name' => 'evil.php',
            'mime_type' => 'application/x-php',
            'size' => 14,
            'sha256' => hash('sha256', '<?php echo 1;'),
            'content' => '<?php echo 1;',
        ]);

        $this->get(route('media.show', ['path' => 'portfolio/evil.php']))->assertNotFound();
    }

    public function test_it_refuses_a_double_extension(): void
    {
        $this->get(route('media.show', ['path' => 'portfolio/evil.jpg.php']))->assertNotFound();
    }

    public function test_it_blocks_path_traversal(): void
    {
        foreach ([
            '../../.env',
            'portfolio/../../../.env',
            '....//....//.env',
        ] as $path) {
            $this->get('/media/'.$path)->assertNotFound();
        }
    }

    public function test_it_404s_a_missing_file(): void
    {
        $this->get(route('media.show', ['path' => 'portfolio/does-not-exist.jpg']))->assertNotFound();
    }

    public function test_it_serves_stored_brochures_as_downloads(): void
    {
        MediaAsset::create([
            'path' => 'brochures/design.pdf',
            'file_name' => 'design.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
            'sha256' => hash('sha256', '%PDF-'),
            'content' => '%PDF-',
        ]);

        $response = $this->get(route('media.show', ['path' => 'brochures/design.pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Asserting the two properties that matter rather than the exact header
        // string: Symfony only quotes a filename when it needs to, and the
        // security-relevant part is 'attachment' -- a PDF must download, never
        // render inline.
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringContainsString('design.pdf', $disposition);

        $this->assertSame('%PDF-', $response->streamedContent());
    }
}
