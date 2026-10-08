<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Decision D17, and the reason it is a decision rather than a default.
 *
 * My own P1 plan originally specified registration at 3 requests per minute
 * per IP. That would break this launch:
 *
 *   - Indonesian mobile carriers use CGNAT heavily, so hundreds of unrelated
 *     subscribers share one egress address.
 *   - A launch event on venue WiFi puts every attendee behind one NAT address.
 *
 * Either way registrant #4 onward receives HTTP 429, and the logs show the
 * application working exactly as configured. The failure is indistinguishable
 * from a successful launch going wrong, which is precisely why it needs a test
 * rather than a comment.
 *
 * Abuse control for registration is the unique email constraint plus the
 * verification requirement. The IP limit that remains is a generous ceiling
 * against scripted floods, not a per-user gate.
 */
class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_registrations_from_one_ip_are_not_blocked(): void
    {
        Notification::fake();

        $sharedIp = ['REMOTE_ADDR' => '114.120.5.17'];

        // Well past the 3-per-minute figure the original plan specified.
        for ($i = 1; $i <= 20; $i++) {
            $response = $this->withServerVariables($sharedIp)->post('/daftar', [
                'name' => "Peserta {$i}",
                'email' => "peserta{$i}@example.test",
                'password' => 'rahasia-yang-panjang',
                'password_confirmation' => 'rahasia-yang-panjang',
            ]);

            $this->assertNotSame(
                429,
                $response->getStatusCode(),
                "Registration {$i} from a shared IP was throttled. A launch on one ".
                'NAT address or carrier CGNAT would fail exactly like this.',
            );

            // Registration signs the customer in, and `guest:customer` would
            // block the next POST. A real launch is 20 separate browsers, so
            // the guard has to be cleared too -- flushSession() alone leaves
            // the user resolved in memory.
            auth('customer')->logout();
            $this->flushSession();
        }

        $this->assertSame(20, Customer::count());
    }

    public function test_repeated_attempts_on_one_email_are_throttled(): void
    {
        Notification::fake();

        // This is where abuse is actually visible: the same address hammering
        // the form. Limiting here costs a legitimate launch nothing, because
        // each attendee registers once with their own address.
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.test',
            'password' => 'rahasia-yang-panjang',
            'password_confirmation' => 'rahasia-yang-panjang',
        ];

        $throttled = false;

        for ($i = 1; $i <= 8; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->post('/daftar', $payload);

            if ($response->getStatusCode() === 429) {
                $throttled = true;
                break;
            }

            $this->flushSession();
        }

        $this->assertTrue($throttled, 'Repeated attempts on one email address should be throttled.');
    }

    public function test_login_attempts_are_throttled_per_email_and_ip(): void
    {
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);

        $throttled = false;

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
                ->post('/masuk', ['email' => $customer->email, 'password' => 'salah']);

            if ($response->getStatusCode() === 429) {
                $throttled = true;
                break;
            }

            $this->flushSession();
        }

        $this->assertTrue($throttled, 'Brute-forcing one account from one IP should be throttled.');
    }

    public function test_one_attacker_cannot_lock_a_victim_out_from_elsewhere(): void
    {
        // The login limiter is keyed on email AND IP together. Keyed on email
        // alone, anyone could lock any account by guessing wrong repeatedly.
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);

        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
                ->post('/masuk', ['email' => $customer->email, 'password' => 'salah']);
            $this->flushSession();
        }

        // The real customer, from their own address, can still sign in.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
            ->post('/masuk', ['email' => $customer->email, 'password' => 'rahasia-yang-panjang']);

        $this->assertNotSame(429, $response->getStatusCode());
        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
