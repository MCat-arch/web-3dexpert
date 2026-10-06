<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class ImportMediaAssets extends Command
{
    protected $signature = 'app:import-media-assets';

    protected $description = 'Import local landing-page images and PDFs into the database';

    public function handle(): int
    {
        $sources = [
            'private media' => storage_path('app/media'),
            'legacy public assets' => public_path('legacy-assets'),
            'public images' => public_path('images'),
        ];

        $imported = 0;
        $skipped = 0;
        $found = 0;

        foreach ($sources as $sourceName => $root) {
            if (! is_dir($root)) {
                $this->warn("Source directory not found ({$sourceName}): {$root}");

                continue;
            }

            foreach ((new Finder())->files()->in($root)->name('/\.(?:jpe?g|png|webp|gif|pdf)$/i') as $file) {
                $found++;
                $path = trim(str_replace('\\', '/', $file->getRelativePathname()), '/');

                try {
                    $asset = MediaAsset::query()->where('path', $path)->first(['id', 'sha256']);
                    $sha256 = hash_file('sha256', $file->getPathname());

                    if ($sha256 === false) {
                        throw new RuntimeException("Unable to hash {$file->getPathname()}");
                    }

                    if ($asset) {
                        if (! hash_equals($asset->sha256, $sha256)) {
                            throw new RuntimeException("Existing database media differs at [{$path}].");
                        }

                        $skipped++;

                        continue;
                    }

                    MediaAsset::storeFile(
                        $path,
                        $file->getPathname(),
                        basename($file->getPathname()),
                    );
                    $imported++;
                    $this->line("Imported {$path}");
                } catch (RuntimeException $exception) {
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }
            }
        }

        if ($found === 0) {
            $this->error('No supported media files were found in the configured source directories.');

            return self::FAILURE;
        }

        $this->info("Media import complete: {$imported} imported, {$skipped} already present.");

        return self::SUCCESS;
    }
}
