<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectUpdate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Demo projects so the tracker can be reviewed end to end.
 *
 * Two records: one mid-flight with progress files, one completed with final
 * deliverables and a closing note. Files are generated for real (PNG + PDF +
 * ZIP) on the private disk, so the download buttons actually work.
 *
 * Idempotent and NON-DESTRUCTIVE: an existing project with the same code is
 * left untouched, and files are only written while it has no attachments. Safe
 * to re-run in production.
 */
class ProjectSeeder extends Seeder
{
    private const IN_PROGRESS_CODE = '3DX-DEMO7A2K';

    private const COMPLETED_CODE = '3DX-DEMO8C4M';

    public function run(): void
    {
        $this->seedInProgress();

        $this->seedCompleted();
    }

    private function seedInProgress(): void
    {
        if (Project::withTrashed()->where('project_code', self::IN_PROGRESS_CODE)->exists()) {
            $this->command?->info('Demo "in progress": sudah ada, dilewati.');

            return;
        }

        $project = Project::create([
            'project_code' => self::IN_PROGRESS_CODE,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi.santoso@example.test',
            'title' => 'Desain Interior Rumah Tinggal Bapak Budi',
            'current_stage' => 'Gambar Kerja',
            'progress_percent' => 55,
            'status' => Project::STATUS_IN_PROGRESS,
            'started_at' => now()->subWeeks(3),
            'estimated_completion_at' => now()->addWeeks(3),
            'is_visible' => true,
        ]);

        $survey = $this->update($project, 1, 'Survei & Pengukuran', ProjectUpdate::STATUS_DONE, now()->subWeeks(3), 'Pengukuran lokasi selesai. Denah eksisting sudah disetujui.');
        $concept = $this->update($project, 2, 'Konsep Desain', ProjectUpdate::STATUS_DONE, now()->subWeeks(2), 'Moodboard dan konsep warna disetujui via WhatsApp.');
        $working = $this->update($project, 3, 'Gambar Kerja', ProjectUpdate::STATUS_IN_PROGRESS, now()->subDays(4), 'Sedang menyusun gambar kerja MEP dan detail furniture.');
        $this->update($project, 4, 'Render Final & Serah Terima', ProjectUpdate::STATUS_PENDING, now()->addWeeks(2), 'Menunggu persetujuan gambar kerja.');

        $this->attach(
            $project,
            $working,
            'Denah & tampak (PDF)',
            ProjectAttachment::KIND_PROGRESS,
            'denah-tampak-rev2.pdf',
            $this->pdf('Denah & Tampak - Revisi 2', [
                'Proyek: Desain Interior Rumah Tinggal Bapak Budi',
                'Tahap: Gambar Kerja',
                'Lantai 1 : ruang tamu, dapur, kamar utama',
                'Lantai 2 : 2 kamar anak, ruang kerja',
                'Skala gambar 1:100, satuan milimeter',
            ]),
            1,
        );

        $this->attach(
            $project,
            $concept,
            'Render konsep ruang tamu',
            ProjectAttachment::KIND_PROGRESS,
            'render-konsep-ruang-tamu.png',
            $this->png(960, 540, [196, 158, 96]),
            2,
        );

        $this->attach(
            $project,
            null,
            'Foto progres di lokasi',
            ProjectAttachment::KIND_PROGRESS,
            'progres-lokasi-minggu-3.png',
            $this->png(960, 540, [88, 104, 120]),
            3,
        );

        $this->command?->info('Demo "in progress" dibuat: '.self::IN_PROGRESS_CODE);
    }

    private function seedCompleted(): void
    {
        if (Project::withTrashed()->where('project_code', self::COMPLETED_CODE)->exists()) {
            $this->command?->info('Demo "completed": sudah ada, dilewati.');

            return;
        }

        $project = Project::create([
            'project_code' => self::COMPLETED_CODE,
            'customer_name' => 'Sari Wulandari',
            'customer_phone' => '081298765432',
            'customer_email' => 'sari.wulandari@example.test',
            'title' => 'Desain Fasad & Landscape Villa Ibu Sari',
            'current_stage' => 'Serah terima',
            'status' => Project::STATUS_COMPLETED,
            'started_at' => now()->subMonths(2),
            'estimated_completion_at' => now()->subWeek(),
            'completed_at' => now()->subDays(5),
            'summary' => 'Seluruh gambar kerja, render final, dan file 3D sudah diserahkan. '
                .'Fasad menggunakan kombinasi batu alam dan kusen kayu, taman depan ditata '
                .'dengan tanaman tropis rendah perawatan. File master dapat dipakai langsung '
                .'oleh kontraktor; hubungi admin bila perlu format tambahan.',
            'is_visible' => true,
        ]);

        $this->update($project, 1, 'Survei & Konsep', ProjectUpdate::STATUS_DONE, now()->subMonths(2), 'Konsep fasad dan layout taman disetujui.');
        $this->update($project, 2, 'Gambar Kerja', ProjectUpdate::STATUS_DONE, now()->subWeeks(4), 'Gambar kerja fasad, potongan, dan detail taman selesai.');
        $this->update($project, 3, 'Render Final', ProjectUpdate::STATUS_DONE, now()->subWeeks(2), 'Enam render final (siang & malam) disetujui.');
        $this->update($project, 4, 'Serah Terima', ProjectUpdate::STATUS_DONE, now()->subDays(5), 'Semua berkas diserahkan melalui halaman ini.');

        $this->attach(
            $project,
            null,
            'Paket gambar kerja lengkap (ZIP)',
            ProjectAttachment::KIND_DELIVERABLE,
            'gambar-kerja-villa-sari.zip',
            $this->zip('gambar-kerja-villa-sari/README.txt', "Paket gambar kerja Villa Ibu Sari\n- fasad.pdf\n- potongan.pdf\n- detail-taman.pdf\n"),
            1,
        );

        $this->attach(
            $project,
            null,
            'Render final fasad (PDF)',
            ProjectAttachment::KIND_DELIVERABLE,
            'render-final-fasad.pdf',
            $this->pdf('Render Final - Fasad Villa Ibu Sari', [
                'Sudut pandang: depan, samping kanan, taman belakang',
                'Material: batu alam andesit, kusen kayu merbau, kaca low-e',
                'Pencahayaan: skenario siang dan malam',
                'Resolusi master 4K, tersedia atas permintaan',
            ]),
            2,
        );

        $this->attach(
            $project,
            null,
            'Foto hasil akhir di lokasi',
            ProjectAttachment::KIND_DELIVERABLE,
            'foto-hasil-akhir.png',
            $this->png(960, 540, [72, 120, 96]),
            3,
        );

        $this->command?->info('Demo "completed" dibuat: '.self::COMPLETED_CODE);
    }

    private function update(Project $project, int $order, string $stage, string $status, $occurredAt, string $note): ProjectUpdate
    {
        return ProjectUpdate::create([
            'project_id' => $project->getKey(),
            'stage_name' => $stage,
            'status' => $status,
            'note' => $note,
            'occurred_at' => $occurredAt,
            'sort_order' => $order,
        ]);
    }

    /**
     * Writes the bytes first, then the row: the model reads mime type and size
     * back off the disk when it saves.
     */
    private function attach(
        Project $project,
        ?ProjectUpdate $update,
        ?string $title,
        string $kind,
        string $fileName,
        string $contents,
        int $order,
    ): ProjectAttachment {
        $path = 'projects/demo/'.$project->project_code.'/'.$update?->getKey().'-'.$fileName;

        Storage::disk(ProjectAttachment::DISK)->put($path, $contents);

        return $project->attachments()->create([
            'project_update_id' => $update?->getKey(),
            'kind' => $kind,
            'title' => $title,
            'path' => $path,
            'original_name' => $fileName,
            'sort_order' => $order,
        ]);
    }

    /**
     * Minimal valid PDF, built by hand so the seeder does not depend on a PDF
     * library. Offsets are computed, so strict viewers open it too.
     *
     * @param  array<int, string>  $lines
     */
    private function pdf(string $title, array $lines): string
    {
        $text = 'BT /F1 18 Tf 56 770 Td ('.$this->pdfText($title).') Tj ET'."\n";

        $y = 735;

        foreach ($lines as $line) {
            $text .= 'BT /F1 11 Tf 56 '.$y.' Td ('.$this->pdfText($line).') Tj ET'."\n";
            $y -= 20;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n".$text.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $value): string
    {
        // Latin-1 only: the built-in Helvetica has no Unicode map.
        $value = str_replace(['&', '(', ')', '\\'], ['and', '\(', '\)', '\\\\'], $value);

        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value;
    }

    /**
     * Solid-colour PNG with a lighter header band, written without GD so the
     * seeder works on any PHP build.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function png(int $width, int $height, array $rgb): string
    {
        $raw = '';

        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00";

            for ($x = 0; $x < $width; $x++) {
                $band = $y < (int) ($height * 0.28);
                $shade = $band ? 34 : 0;

                $raw .= chr(min(255, $rgb[0] + $shade))
                    .chr(min(255, $rgb[1] + $shade))
                    .chr(min(255, $rgb[2] + $shade));
            }
        }

        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', $ihdr)
            .$this->pngChunk('IDAT', gzcompress($raw, 9))
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private function zip(string $entryName, string $contents): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ZipArchive tidak tersedia untuk membuat file demo.');
        }

        $temporary = tempnam(sys_get_temp_dir(), '3dx-zip-');

        if ($temporary === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk ZIP demo.');
        }

        $zip = new \ZipArchive;
        $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString($entryName, $contents);
        $zip->close();

        $bytes = (string) file_get_contents($temporary);
        @unlink($temporary);

        return $bytes;
    }
}
