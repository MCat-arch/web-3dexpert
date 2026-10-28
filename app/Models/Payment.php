<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    /** QRIS through Midtrans (the default for paid orders). */
    public const TYPE_QRIS = 'qris';

    /** Manual bank transfer, chosen at checkout. */
    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    /** Private disk holding uploaded transfer receipts. */
    public const PROOF_DISK = 'proofs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLEMENT = 'settlement';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'order_id',
        'gross_amount',
        'status',
        'payment_type',
        'midtrans_transaction_id',
        'raw_response',
        'expires_at',
        'paid_at',
        'payment_proof_path',
        'payment_proof_original_name',
        'payment_proof_note',
        'payment_proof_uploaded_at',
        'validated_at',
        'validated_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'raw_response' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The staff member who validated a manual bank transfer. */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isManual(): bool
    {
        return $this->payment_type === self::TYPE_BANK_TRANSFER;
    }

    public function hasProof(): bool
    {
        return filled($this->payment_proof_path);
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLEMENT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** A pending payment whose deadline has passed. */
    public function isDueForExpiry(?Carbon $at = null): bool
    {
        return $this->isPending()
            && $this->expires_at !== null
            && $this->expires_at->lessThanOrEqualTo($at ?? now());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SETTLEMENT);
    }

    public function scopeDueForExpiry(Builder $query): Builder
    {
        return $query->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * The QR image URL returned by Midtrans. Stored inside raw_response so no
     * extra columns are needed and the original gateway payload stays intact.
     */
    public function qrUrl(): ?string
    {
        $url = data_get($this->raw_response, 'qr_url')
            ?? data_get($this->raw_response, 'charge.actions');

        if (is_array($url)) {
            foreach ($url as $action) {
                if (($action['name'] ?? null) === 'generate-qr-code') {
                    return $action['url'] ?? null;
                }
            }

            return null;
        }

        return filled($url) ? (string) $url : null;
    }

    public function qrString(): ?string
    {
        return data_get($this->raw_response, 'qr_string')
            ?? data_get($this->raw_response, 'charge.qr_string');
    }

    public function chargeError(): ?string
    {
        return data_get($this->raw_response, 'charge_error');
    }

    /**
     * Random, date-prefixed order id. Not sequential: it appears on the public
     * payment page and in emails.
     */
    public static function generateOrderId(string $prefix): string
    {
        return $prefix.'-'.date('Ymd').'-'.Str::upper(Str::random(8));
    }
}
