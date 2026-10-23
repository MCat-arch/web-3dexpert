<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Actions\Payments\RefundPayment;
use App\Models\CourseEnrollment;
use App\Models\EventRegistration;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
            ])
            ->recordActions([
                ViewAction::make(),

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
