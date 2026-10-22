<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Models\AuditLog;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pembayaran')
                ->columns(3)
                ->schema([
                    TextEntry::make('order_id')->label('Order ID')->copyable(),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => self::statusLabel($state))
                        ->color(fn (string $state) => match ($state) {
                            Payment::STATUS_SETTLEMENT => 'success',
                            Payment::STATUS_PENDING => 'warning',
                            Payment::STATUS_REFUNDED => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('gross_amount')
                        ->label('Nominal')
                        ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                    TextEntry::make('payment_type')->label('Metode')->placeholder('-'),
                    TextEntry::make('midtrans_transaction_id')->label('Midtrans ID')->placeholder('-')->copyable(),
                    TextEntry::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('expires_at')->label('Kedaluwarsa')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                ]),

            Section::make('Payable')
                ->schema([
                    TextEntry::make('payable_reference')
                        ->label('Pendaftaran Kursus')
                        ->state(function (Payment $record) {
                            $enrollment = CourseEnrollment::query()->find($record->payable_id);

                            return $enrollment
                                ? 'Enrollment #'.$enrollment->getKey()
                                : 'Payable #'.$record->payable_id;
                        })
                        ->url(fn (Payment $record) => $record->payable_type === CourseEnrollment::class
                            ? CourseEnrollmentResource::getUrl('view', ['record' => $record->payable_id])
                            : null),
                    TextEntry::make('course_title')
                        ->label('Kursus')
                        ->state(function (Payment $record) {
                            $enrollment = CourseEnrollment::query()->with('course')->find($record->payable_id);

                            return $enrollment?->course?->getTranslation('title', 'id') ?? '-';
                        }),
                ]),

            Section::make('Respons Midtrans')
                ->collapsed()
                ->schema([
                    TextEntry::make('raw_response_json')
                        ->label('Raw response')
                        ->state(fn (Payment $record) => $record->raw_response
                            ? json_encode($record->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : 'Tidak ada respons tersimpan.')
                        ->visible(fn () => auth()->user()?->can(User::AREA_PAYMENTS) ?? false),
                ]),

            ViewEntry::make('history')
                ->view('filament.payments.history')
                ->viewData(function (Payment $record) {
                    $logs = AuditLog::query()
                        ->where('subject_type', $record->getMorphClass())
                        ->where('subject_id', $record->getKey())
                        ->with('actor')
                        ->latest('created_at')
                        ->limit(20)
                        ->get();

                    return ['logs' => $logs];
                })
                ->columnSpanFull(),
        ]);
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            Payment::STATUS_PENDING => 'Menunggu',
            Payment::STATUS_SETTLEMENT => 'Settlement',
            Payment::STATUS_EXPIRED => 'Kedaluwarsa',
            Payment::STATUS_CANCELLED => 'Dibatalkan',
            Payment::STATUS_FAILED => 'Gagal',
            Payment::STATUS_REFUNDED => 'Refund',
            default => $status,
        };
    }
}
