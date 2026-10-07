<?php

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

it('stores media bytes on the private disk instead of in the database', function () {
    Storage::fake('media');

    $source = tempnam(sys_get_temp_dir(), 'media-asset-');
    file_put_contents($source, 'private image bytes');

    try {
        $asset = MediaAsset::storeFile('portfolio/photo.jpg', $source, 'photo.jpg');
    } finally {
        unlink($source);
    }

    Storage::disk('media')->assertExists('portfolio/photo.jpg');
    expect($asset->storage_path)->toBe('portfolio/photo.jpg')
        ->and($asset->content)->toBe('')
        ->and($asset->size)->toBe(strlen('private image bytes'));
});

it('serves a stored image with the content type this app chooses', function () {
    MediaAsset::create([
        'path' => 'portfolio/photo.jpg',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 4,
        'sha256' => hash('sha256', 'image'),
        'content' => 'image',
    ]);

    $this->get(route('media.show', ['path' => 'portfolio/photo.jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSee('image');
});

it('streams media from the private disk', function () {
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

    $this->get(route('media.show', ['path' => 'portfolio/photo.jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertSee('image');
});

it('refuses to serve a php file even when one is stored', function () {
    MediaAsset::create([
        'path' => 'portfolio/evil.php',
        'file_name' => 'evil.php',
        'mime_type' => 'application/x-php',
        'size' => 14,
        'sha256' => hash('sha256', '<?php echo 1;'),
        'content' => '<?php echo 1;',
    ]);

    $this->get(route('media.show', ['path' => 'portfolio/evil.php']))->assertNotFound();
});

it('refuses a double extension', function () {
    $this->get(route('media.show', ['path' => 'portfolio/evil.jpg.php']))->assertNotFound();
});

it('blocks path traversal', function () {
    foreach ([
        '../../.env',
        'portfolio/../../../.env',
        '....//....//.env',
    ] as $path) {
        $this->get('/media/'.$path)->assertNotFound();
    }
});

it('404s a missing file', function () {
    $this->get(route('media.show', ['path' => 'portfolio/does-not-exist.jpg']))->assertNotFound();
});

it('serves stored brochures as downloads', function () {
    MediaAsset::create([
        'path' => 'brochures/design.pdf',
        'file_name' => 'design.pdf',
        'mime_type' => 'application/pdf',
        'size' => 5,
        'sha256' => hash('sha256', '%PDF-'),
        'content' => '%PDF-',
    ]);

    $this->get(route('media.show', ['path' => 'brochures/design.pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="design.pdf"')
        ->assertSee('%PDF-', false);
});
