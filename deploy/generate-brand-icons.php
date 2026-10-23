<?php

/**
 * Generate the browser/tab icons from the left-hand 3D Expert mark in
 * public/images/logo.png.
 *
 * The source logo is a wide lockup (mark + wordmark). A favicon made by simply
 * shrinking the whole lockup is unreadable at 16x16, so this script crops the
 * mark and masks out the "Expert" letterforms that overlap its bounding box.
 *
 * Usage: php deploy/generate-brand-icons.php
 */

$root = dirname(__DIR__);
$source = $root.'/public/images/logo.png';

if (! is_file($source)) {
    fwrite(STDERR, "Logo not found: {$source}\n");
    exit(1);
}

$logo = imagecreatefrompng($source);

if ($logo === false) {
    fwrite(STDERR, "Could not read PNG: {$source}\n");
    exit(1);
}

// The left mark occupies x=0..1131, y=0..914 in the 2893x915 lockup.
$markWidth = 1132;
$markHeight = 915;
$apexX = 566.0;

$mark = imagecreatetruecolor($markWidth, $markHeight);
imagealphablending($mark, false);
imagesavealpha($mark, true);
imagefill($mark, 0, 0, imagecolorallocatealpha($mark, 255, 255, 255, 127));

imagecopyresampled(
    $mark,
    $logo,
    0,
    0,
    0,
    0,
    $markWidth,
    $markHeight,
    $markWidth,
    $markHeight,
);

/*
 * Make everything outside the triangle transparent. This removes the "Expert"
 * and tagline glyphs that share the same rectangular crop as the narrowing
 * lower half of the mark.
 */
$transparent = imagecolorallocatealpha($mark, 0, 0, 0, 127);

for ($y = 0; $y < $markHeight; $y++) {
    $left = (int) floor($apexX * ($y / $markHeight));
    $right = (int) ceil($markWidth - ($apexX * ($y / $markHeight)));

    for ($x = 0; $x < $left; $x++) {
        imagesetpixel($mark, $x, $y, $transparent);
    }

    for ($x = $right; $x < $markWidth; $x++) {
        imagesetpixel($mark, $x, $y, $transparent);
    }
}

/**
 * @return GdImage
 */
function iconCanvas(int $size, bool $opaqueWhite = false)
{
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    $background = $opaqueWhite
        ? imagecolorallocate($canvas, 255, 255, 255)
        : imagecolorallocatealpha($canvas, 255, 255, 255, 127);

    imagefill($canvas, 0, 0, $background);

    return $canvas;
}

/**
 * @param  GdImage  $mark
 * @return GdImage
 */
function renderIcon($mark, int $size, bool $opaqueWhite = false)
{
    global $markWidth, $markHeight;

    $canvas = iconCanvas($size, $opaqueWhite);

    // 12% breathing room keeps the red triangle edge off the crop line.
    $inner = $size * 0.88;
    $drawWidth = $inner;
    $drawHeight = $drawWidth * ($markHeight / $markWidth);

    if ($drawHeight > $inner) {
        $drawHeight = $inner;
        $drawWidth = $drawHeight * ($markWidth / $markHeight);
    }

    $x = (int) round(($size - $drawWidth) / 2);
    $y = (int) round(($size - $drawHeight) / 2);

    imagecopyresampled(
        $canvas,
        $mark,
        $x,
        $y,
        0,
        0,
        (int) round($drawWidth),
        (int) round($drawHeight),
        $markWidth,
        $markHeight,
    );

    return $canvas;
}

function writePng($canvas, string $path): void
{
    if (! imagepng($canvas, $path)) {
        fwrite(STDERR, "Could not write {$path}\n");
        exit(1);
    }
}

$pngs = [
    'favicon-16x16.png' => [16, false],
    'favicon-32x32.png' => [32, false],
    'favicon-48x48.png' => [48, false],
    'android-chrome-192x192.png' => [192, false],
    'android-chrome-512x512.png' => [512, false],
    // iOS composites transparency on black; white keeps the dark half of the
    // mark visible.
    'apple-touch-icon.png' => [180, true],
];

foreach ($pngs as $name => [$size, $opaqueWhite]) {
    $canvas = renderIcon($mark, $size, $opaqueWhite);
    writePng($canvas, $root.'/public/'.$name);
    imagedestroy($canvas);
}

/*
 * ICO with PNG payloads (supported by every browser we target).
 * Header: ICONDIR + one ICONDIRENTRY per image.
 */
$icoSizes = [16, 32, 48];
$images = [];

foreach ($icoSizes as $size) {
    $canvas = renderIcon($mark, $size);
    ob_start();
    imagepng($canvas);
    $images[] = ob_get_clean();
    imagedestroy($canvas);
}

$header = pack('vvv', 0, 1, count($images));
$offset = 6 + (16 * count($images));
$entries = '';
$payload = '';

foreach ($images as $index => $bytes) {
    $size = $icoSizes[$index];

    $entries .= pack(
        'CCCCvvVV',
        $size === 256 ? 0 : $size,
        $size === 256 ? 0 : $size,
        0,
        0,
        1,
        32,
        strlen($bytes),
        $offset,
    );

    $payload .= $bytes;
    $offset += strlen($bytes);
}

file_put_contents($root.'/public/favicon.ico', $header.$entries.$payload);

// Web app manifest.
$manifest = [
    'name' => '3D Expert',
    'short_name' => '3D Expert',
    'description' => '3D Expert - Show Your Imagination',
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#111111',
    'icons' => [
        [
            'src' => '/android-chrome-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/android-chrome-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],
];

file_put_contents(
    $root.'/public/site.webmanifest',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

echo "Generated favicon.ico, PNG icons, and site.webmanifest.\n";
