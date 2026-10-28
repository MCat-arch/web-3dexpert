<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\RejectPaymentProof;
use App\Actions\Payments\ValidateManualPayment;
use App\Models\CourseEnrollment;
use App\Models\EventRegistration;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                // A morphTo cannot eager-load a child relation that only exists
                // on one payable class; morphWith dispatches by actual type.
                'payable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    CourseEnrollment::class => ['course'],
                    EventRegistration::class => ['event'],
                ]),
            ]))
            ->columns([
                TextColumn::make('order_id')->label('Order ID')->searchable()->copyable(),
                TextColumn::make('payable_kind')
                    ->label('Jenis')
                    ->badge()
                    ->state(fn (Payment $record) => match ($record->payable_type) {
                        CourseEnrollment::class => 'Kursus',
                        EventRegistration::class => 'Acara',
                        default => '-',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Kursus' => 'info',
                        'Acara' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payable_item')
                    ->label('Item')
                    ->state(fn (Payment $record) => match (true) {
                        $record->payable instanceof CourseEnrollment => $record->payable->course?->getTranslation('title', 'id') ?? '-',
                        $record->payable instanceof EventRegistration => $record->payable->event?->getTranslation('title', 'id') ?? '-',
                        default => '-',
                    })
                    ->wrap(),
                TextColumn::make('gross_amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Payment::STATUS_PENDING => 'Menunggu',
                        Payment::STATUS_SETTLEMENT => 'Settlement',
                        Payment::STATUS_EXPIRED => 'Kedaluwarsa',
                        Payment::STATUS_CANCELLED => 'Dibatalkan',
                        Payment::STATUS_FAILED => 'Gagal',
                        Payment::STATUS_REFUNDED => 'Refund',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        Payment::STATUS_SETTLEMENT => 'success',
                        Payment::STATUS_PENDING => 'warning',
                        Payment::STATUS_REFUNDED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_type')->label('Metode')->placeholder('-')->toggleable(),
                TextColumn::make('midtrans_transaction_id')->label('Midtrans ID')->toggleable()->searchable(),
                TextColumn::make('payment_proof_uploaded_at')
                    ->label('Bukti diunggah')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Payment::STATUS_PENDING => 'Menunggu',
                        Payment::STATUS_SETTLEMENT => 'Settlement',
                        Payment::STATUS_EXPIRED => 'Kedaluwarsa',
                        Payment::STATUS_CANCELLED => 'Dibatalkan',
                        Payment::STATUS_FAILED => 'Gagal',
                        Payment::STATUS_REFUNDED => 'Refund',
                    ]),
                Filter::make('paid_range')
                    ->label('Tanggal bayar')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date));
                    }),
                Filter::make('proof_uploaded')
                    ->label('Sudah unggah bukti')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('payment_proof_path')),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('view_proof')
                    ->label('Lihat bukti')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (Payment $record) => $record->hasProof())
                    ->url(fn (Payment $record) => route('admin.payments.proof', ['payment' => $record]))
                    ->openUrlInNewTab(),

                Action::make('validate_manual')
                    ->label('Validasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->isPending() && $record->hasProof())
                    ->requiresConfirmation()
                    ->modalHeading('Validasi pembayaran manual')
                    ->modalDescription('Pastikan nominal transfer sudah sesuai. Status akan menjadi settlement dan akses kursus/acara langsung diberikan.')
                    ->action(function (Payment $record) {
                        try {
                            app(ValidateManualPayment::class)($record, auth()->user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Validasi gagal')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Pembayaran tervalidasi dan akses diberikan')
                            ->send();
                    }),

                Action::make('reject_proof')
                    ->label('Tolak bukti')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->isPending() && $record->hasProof())
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan penolakan')
                            ->rows(3)
                            ->required()
                            ->maxLength(500),
                    ])
                    ->modalHeading('Tolak bukti pembayaran')
                    ->modalDescription('Pembayaran akan dibatalkan dan kursi/akses yang ditahan akan dilepas. Tindakan ini tidak dapat dibatalkan.')
                    ->action(function (Payment $record, array $data) {
                        try {
                            app(RejectPaymentProof::class)(
                                $record,
                                (string) ($data['reason'] ?? ''),
                                auth()->user(),
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal menolak bukti')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Bukti pembayaran ditolak dan akses dilepas')
                            ->send();
                    }),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->isSettled())
                    ->requiresConfirmation()
                    ->modalHeading('Refund pembayaran')
                    ->modalDescription('Status pembayaran akan menjadi refunded dan akses kursus atau pendaftaran acara terkait akan dicabut. Tindakan ini tidak dapat dibatalkan.')
                    ->action(function (Payment $record) {
                        app(RefundPayment::class)($record);

                        Notification::make()
                            ->success()
                            ->title('Pembayaran direfund dan akses dicabut')
                            ->send();
                    }),
            ]);
    }
}
