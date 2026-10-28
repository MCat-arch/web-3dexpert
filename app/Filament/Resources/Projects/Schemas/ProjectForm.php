<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kode proyek')
                ->schema([
                    TextInput::make('project_code')
                        ->label('Kode proyek')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Dibuat otomatis saat disimpan')
                        /*
                         * Read-only. The code is random, non-sequential, and
                         * avoids characters people misread -- a hand-typed one
                         * could be guessable, which would make the public
                         * tracker an enumeration oracle (D2).
                         */
                        ->helperText('Dibuat otomatis. Kirimkan kode ini ke pelanggan.')
                        ->visibleOn('edit'),
                ]),

            Section::make('Pelanggan')
                ->columns(2)
                ->description('Kontak di bawah ini dipakai sebagai verifikasi kedua saat pelanggan melacak proyek. Isi minimal satu.')
                ->schema([
                    TextInput::make('customer_name')->label('Nama pelanggan')->required(),

                    TextInput::make('customer_phone')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        // Normalised on save by the model, so a number typed
                        // here matches one typed on the tracker regardless of
                        // formatting (App\Support\PhoneNumber).
                        ->helperText('Format apa pun; akan dinormalkan ke 08xxxxxxxxxx.')
                        ->requiredWithout('customer_email'),

                    TextInput::make('customer_email')
                        ->label('Email')
                        ->email()
                        ->requiredWithout('customer_phone'),
                ]),

            Section::make('Progres & hasil akhir')
                ->columns(2)
                ->description('Persentase dan penjelasan di bawah ini tampil di halaman Lacak Proyek milik pelanggan.')
                ->schema([
                    Slider::make('progress_percent')
                        ->label('Progres (%)')
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(5)
                        ->default(0)
                        ->helperText('Status Selesai otomatis menjadi 100%.')
                        ->columnSpanFull(),

                    Textarea::make('summary')
                        ->label('Penjelasan hasil akhir')
                        ->rows(4)
                        ->helperText('Tampil di bagian "Hasil Akhir" ketika status Selesai. Ringkas: apa yang diserahkan dan catatan pemakaian.')
                        ->columnSpanFull(),
                ]),

            Section::make('Status & tanggal')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Judul proyek')->required()->columnSpanFull(),

                    Select::make('status')
                        ->label('Status')
                        ->options(
                            collect(Project::statuses())
                                ->mapWithKeys(fn (string $s) => [$s => __('tracker.status_'.$s)])
                                ->all()
                        )
                        ->default(Project::STATUS_IN_PROGRESS)
                        ->required()
                        // Cancelled stays hidden from the tracker; completed is
                        // shown so the owner can download the final files.
                        ->helperText('Dibatalkan tidak tampil di pelacakan publik.'),

                    TextInput::make('current_stage')
                        ->label('Tahap saat ini')
                        ->helperText('Ditampilkan ke pelanggan, mis. "Gambar Kerja".'),

                    DatePicker::make('started_at')->label('Mulai'),
                    DatePicker::make('estimated_completion_at')->label('Perkiraan selesai'),
                    DatePicker::make('completed_at')
                        ->label('Tanggal selesai')
                        ->helperText('Diisi otomatis saat status diubah ke Selesai.'),

                    Toggle::make('is_visible')
                        ->label('Tampilkan di pelacakan')
                        ->default(true)
                        ->helperText('Matikan untuk menyembunyikan proyek tanpa menghapusnya.'),
                ]),
        ]);
    }
}
