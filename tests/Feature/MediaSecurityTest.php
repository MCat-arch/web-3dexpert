<?php

use Illuminate\Http\UploadedFile;
use App\Models\MediaAsset;

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
