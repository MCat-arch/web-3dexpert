<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

class AutoTranslator
{
    /** Common dictionary pairs for architectural, 3D, and educational content */
    protected static array $dictionary = [
        'dasar desain 3d'   => '3D design fundamentals',
        'untuk pemula'      => 'for beginners',
        'pemula'            => 'beginner',
        'menengah'          => 'intermediate',
        'lanjutan'          => 'advanced',
        'mentoring'         => 'mentoring',
        'kursus'            => 'course',
        'pelatihan'         => 'training',
        'acara'             => 'event',
        'kegiatan'          => 'activities',
        'workshop'          => 'workshop',
        'arsitektur'        => 'architecture',
        'desain interior'   => 'interior design',
        'desain eksterior'  => 'exterior design',
        'desain rumah'      => 'house design',
        'visualisasi 3d'    => '3D visualization',
        'animasi'           => 'animation',
        'gratis'            => 'free',
        'berbayar'          => 'paid',
        'lokasi'            => 'location',
        'jadwal'            => 'schedule',
        'waktu'             => 'time',
        'pendaftaran'       => 'registration',
        'tentang'           => 'about',
        'ikhtisar'          => 'overview',
        'spesifikasi'       => 'specifications',
        'selesai'           => 'completed',
        'lantai'            => 'floors',
        'kamar tidur'       => 'bedrooms',
        'kamar mandi'       => 'bathrooms',
        'luas tanah'        => 'land area',
        'luas bangunan'     => 'building area',
    ];

    public static function translateToEnglish(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        // Try free translation API if reachable within 1.5 seconds
        try {
            $response = Http::timeout(1.5)
                ->withoutVerifying()
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => mb_substr($text, 0, 500),
                    'langpair' => 'id|en',
                ]);

            if ($response->successful()) {
                $translated = $response->json('responseData.translatedText');
                if (filled($translated) && ! str_contains(strtolower($translated), 'quota')) {
                    return (string) $translated;
                }
            }
        } catch (Throwable) {
            // Fallback gracefully
        }

        // Dictionary-assisted translation
        $translated = $text;
        foreach (self::$dictionary as $idPhrase => $enPhrase) {
            $pattern = '/\b' . preg_quote($idPhrase, '/') . '\b/iu';
            $translated = preg_replace($pattern, $enPhrase, $translated);
        }

        return $translated;
    }
}
