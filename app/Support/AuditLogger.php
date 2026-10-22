<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\JobApplicationDocument;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Explicit audit writer.
 *
 * Observers cover publish-state changes; the rest of the sensitive actions
 * (role changes, refunds, enrollment grants, submissions, downloads) call
 * this class directly so the business operation and the audit entry happen in
 * one transaction.
 */
class AuditLogger
{
    /**
     * Never persist these field values. Storing a field name or a redaction
     * marker is enough to prove the change happened.
     *
     * @var array<int, string>
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'raw_response',
        'internal_notes',
        'cover_letter',
        'submitted_text',
        'feedback',
        'token',
        'secret',
        'api_key',
    ];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?Model $actor = null,
        ?string $description = null,
    ): AuditLog {
        $actor ??= $this->currentActor();

        $request = $this->request();

        return AuditLog::query()->create([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $this->redact($old),
            'new_values' => $this->redact($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent()
                ? mb_substr((string) $request->userAgent(), 0, 255)
                : null,
            'created_at' => now(),
        ]);
    }

    public function published(Model $subject, bool $published, ?string $event = null): AuditLog
    {
        return $this->log(
            $event ?? ($published ? 'content.published' : 'content.unpublished'),
            $subject,
            ['is_published' => ! $published],
            ['is_published' => $published],
        );
    }

    public function statusChanged(Model $subject, string $old, string $new, ?string $event = null): AuditLog
    {
        return $this->log(
            $event ?? 'status.changed',
            $subject,
            ['status' => $old],
            ['status' => $new],
        );
    }

    public function roleChanged(User $user, string $old, string $new): AuditLog
    {
        return $this->log('role.changed', $user, ['role' => $old], ['role' => $new]);
    }

    public function deactivated(Model $subject, string $event = 'user.deactivated'): AuditLog
    {
        return $this->log($event, $subject, ['is_active' => true], ['is_active' => false]);
    }

    public function reactivated(Model $subject, string $event = 'user.reactivated'): AuditLog
    {
        return $this->log($event, $subject, ['is_active' => false], ['is_active' => true]);
    }

    public function refunded(Payment $payment): AuditLog
    {
        return $this->log(
            'payment.refunded',
            $payment,
            ['status' => $payment->status],
            [
                'status' => Payment::STATUS_REFUNDED,
                'order_id' => $payment->order_id,
                'gross_amount' => (string) $payment->gross_amount,
            ],
            null,
            'Refund processed for order '.$payment->order_id,
        );
    }

    public function documentDownloaded(JobApplicationDocument $document): AuditLog
    {
        return $this->log(
            'document.downloaded',
            $document->application,
            [],
            ['document_id' => $document->getKey(), 'type' => $document->type],
        );
    }

    private function currentActor(): ?Model
    {
        return auth('web')->user() ?? auth('customer')->user();
    }

    private function request(): ?Request
    {
        if (app()->runningInConsole() && ! app()->bound('request')) {
            return null;
        }

        return request();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(mb_strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $values[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
