<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class ImportMediaAssets extends Command
{
    protected $signature = 'app:import-media-assets';

    protected $description = 'Import local landing-page images and PDFs into private file storage';

    public function handle(): int
    {
        $sources = [
            [
                'name' => 'managed media',
                'root' => storage_path('app/media'),
                'prefix' => '',
                'excluded' => [],
            ],
            [
                'name' => 'public images',
                'root' => public_path('images'),
                'prefix' => 'images/',
                'excluded' => ['portofolio/project-1.png'],
            ],
        ];

        $imported = 0;
        $skipped = 0;
        $found = 0;

        foreach ($sources as $source) {
            $sourceName = $source['name'];
            $root = $source['root'];

            if (! is_dir($root)) {
                $this->warn("Source directory not found ({$sourceName}): {$root}");

                continue;
            }

            foreach ((new Finder)->files()->in($root)->name('/\.(?:jpe?g|png|webp|gif|pdf)$/i') as $file) {
                $relativePath = trim(str_replace('\\', '/', $file->getRelativePathname()), '/');

                if (in_array($relativePath, $source['excluded'], true)) {
                    continue;
                }

                $found++;
                $path = $source['prefix'].$relativePath;

                try {
                    $asset = MediaAsset::storeFile(
                        $path,
                        $file->getPathname(),
                        basename($file->getPathname()),
                    );
                } catch (RuntimeException $exception) {
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }

                if ($asset->wasRecentlyCreated || $asset->wasChanged('storage_path')) {
                    $imported++;
                    $this->line("Imported {$path}");
                } else {
                    $skipped++;
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
