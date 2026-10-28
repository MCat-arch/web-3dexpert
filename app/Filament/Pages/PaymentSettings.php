<?php

namespace App\Filament\Pages;

use App\Filament\Support\NavGroup;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Settings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Bank details for the manual transfer flow.
 *
 * The keys (`payment_bank_name`, `payment_bank_account`, `payment_bank_holder`)
 * exist in Settings::SCHEMA, but the generic Site Settings screen only lists
 * rows that already exist in the table -- and these were never created, so the
 * payment page had no account to show. This page writes them through
 * Settings::set(), which also makes them appear in Site Settings afterwards.
 *
 * Access is the payments area: the same staff who validate transfer proofs.
 */
class PaymentSettings extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?int $navigationSort = 93;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Pengaturan Pembayaran';

    protected static ?string $title = 'Pengaturan Pembayaran';

    protected string $view = 'filament.pages.payment-settings';

    public string $bankName = '';

    public string $bankAccount = '';

    public string $bankHolder = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(User::AREA_PAYMENTS) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->bankName = (string) Settings::get('payment_bank_name', '');
        $this->bankAccount = (string) Settings::get('payment_bank_account', '');
        $this->bankHolder = (string) Settings::get('payment_bank_holder', '');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'bankName' => ['required', 'string', 'max:100'],
            'bankAccount' => ['required', 'string', 'max:60'],
            'bankHolder' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'bankName' => 'nama bank',
            'bankAccount' => 'nomor rekening',
            'bankHolder' => 'nama pemilik rekening',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $keys = [
            'bankName' => 'payment_bank_name',
            'bankAccount' => 'payment_bank_account',
            'bankHolder' => 'payment_bank_holder',
        ];

        $old = [];
        $new = [];

        foreach ($keys as $field => $key) {
            $old[$key] = (string) Settings::get($key, '');
            $new[$key] = trim($data[$field]);

            Settings::set($key, $new[$key]);
        }

        app(AuditLogger::class)->log(
            'payment.bank_settings_updated',
            null,
            $old,
            $new,
            description: 'Rekening tujuan transfer pembayaran manual diperbarui.',
        );

        Notification::make()
            ->title('Rekening pembayaran disimpan')
            ->body('Data ini sekarang tampil di halaman pembayaran pelanggan.')
            ->success()
            ->send();
    }
}
