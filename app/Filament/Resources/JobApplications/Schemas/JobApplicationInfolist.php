<?php

namespace App\Filament\Resources\JobApplications\Schemas;

use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pelamar')
                ->columns(2)
                ->schema([
                    // The snapshot, deliberately. Reading through the customer
                    // relation would show nothing once the account is deleted,
                    // which is the case this whole design exists to survive.
                    TextEntry::make('applicant_name')->label('Nama'),
                    TextEntry::make('applicant_email')->label('Email')->copyable(),
                    TextEntry::make('applicant_phone')->label('WhatsApp')->copyable(),
                    TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i'),

                    TextEntry::make('account_state')
                        ->label('Akun pelamar')
                        ->state(fn (JobApplication $record) => $record->applicantAccountDeleted()
                            ? 'Sudah dihapus — data lamaran tetap tersimpan'
                            : 'Aktif')
                        ->badge()
                        ->color(fn (JobApplication $record) => $record->applicantAccountDeleted() ? 'gray' : 'success'),
                ]),

            Section::make('Posisi')
                ->columns(3)
                ->schema([
                    TextEntry::make('vacancy.title')
                        ->label('Posisi')
                        ->state(fn (JobApplication $record) => $record->vacancy?->getTranslation('title', 'id')),
                    TextEntry::make('vacancy.department')->label('Departemen'),
                    TextEntry::make('vacancy.location')->label('Lokasi'),
                ]),

            Section::make('Surat lamaran')
                ->schema([
                    TextEntry::make('cover_letter')
                        ->hiddenLabel()
                        ->placeholder('Tidak ada surat lamaran.')
                        ->columnSpanFull(),

                    // Legacy rows only: the portfolio used to be a link. New
                    // applications send a PDF document instead, so the row is
                    // hidden rather than showing an empty placeholder.
                    TextEntry::make('portfolio_url')
                        ->label('Link portofolio (lamaran lama)')
                        ->visible(fn (JobApplication $record) => filled($record->portfolio_url))
                        // Rendered as a real link, but only http/https URLs
                        // pass validation on submission.
                        ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                        ->columnSpanFull(),
                ]),

            Section::make('Dokumen')
                ->schema([
                    TextEntry::make('documents')
                        ->hiddenLabel()
                        ->state(function (JobApplication $record) {
                            if ($record->documents->isEmpty()) {
                                return 'Tidak ada dokumen.';
                            }

                            /*
                             * Links go through the staff-only document route.
                             * There is no public or signed URL for these files
                             * -- the disk sits under storage_path, so this
                             * controller is the only path to the bytes and it
                             * authorises first.
                             *
                             * The route serves inline (Content-Disposition:
                             * inline), so "Lihat" opens the PDF in a new tab
                             * instead of downloading it.
                             */
                            return $record->documents
                                ->map(fn ($d) => sprintf(
                                    '<span class="font-medium">%s</span> &middot; <a href="%s" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">Lihat</a> <span class="text-gray-500">(%s, %s)</span>',
                                    e(match ($d->type) {
                                        JobApplicationDocument::TYPE_CV => 'CV',
                                        JobApplicationDocument::TYPE_PORTFOLIO => 'Portofolio',
                                        JobApplicationDocument::TYPE_CERTIFICATE => 'Sertifikat',
                                        default => 'Dokumen pendukung',
                                    }),
                                    route('applications.document', ['document' => $d->getKey()]),
                                    e($d->original_filename),
                                    e($d->humanSize()),
                                ))
                                ->implode('<br>');
                        })
                        ->html()
                        ->columnSpanFull(),
                ]),

            Section::make('Catatan internal')
                ->schema([
                    TextEntry::make('internal_notes')
                        ->hiddenLabel()
                        ->placeholder('Belum ada catatan.')
                        // Staff-only: $hidden on the model, gated by
                        // JobApplicationPolicy::viewNotes, and never included
                        // in any applicant-facing response.
                        ->columnSpanFull(),

                    TextEntry::make('reviewer.name')
                        ->label('Ditinjau oleh')
                        ->placeholder('Belum ditinjau.'),
                ]),
        ]);
    }
}
