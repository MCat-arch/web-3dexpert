<?php

namespace App\Console\Commands;

use App\Actions\Payments\ExpirePayment;
use App\Models\Payment;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Expire unpaid course and event payments past their deadline and release held seats';

    public function handle(ExpirePayment $expire): int
    {
        $expired = 0;

        Payment::query()
            ->dueForExpiry()
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($expire, &$expired) {
                foreach ($payments as $payment) {
                    $result = $expire($payment);

                    if (! $result->isPending()) {
                        $expired++;
                    }
                }
            });

        $this->info("Expired {$expired} pending payment(s).");

        return self::SUCCESS;
    }
}
