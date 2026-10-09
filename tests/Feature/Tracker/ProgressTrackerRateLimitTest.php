<?php

namespace Tests\Feature\Tracker;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The second half of D2.
 *
 * A second factor only helps if it cannot be brute-forced. The code is random
 * over a 28-character alphabet, so enumerating codes is hopeless -- but once
 * someone HAS a real code (forwarded message, shared screenshot), the only
 * thing between them and the project is the contact detail. That is a phone
 * number: guessable in a way a password is not.
 *
 * Hence the per-CODE limit. A per-IP limit alone would not help, because that
 * attack is trivially distributed.
 */
class ProgressTrackerRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guessing_contacts_against_one_code_is_throttled_across_many_ips(): void
    {
        $project = Project::factory()->create(['customer_phone' => '081234567890']);

        $throttled = false;

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->post('/lacak-proyek', [
                    'project_code' => $project->project_code,
                    'verification' => '08000000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                ]);

            if ($response->getStatusCode() === 429) {
                $throttled = true;
                break;
            }

            $this->flushSession();
        }

        $this->assertTrue(
            $throttled,
            'Guessing contact details against one known code must be throttled '.
            'even from different addresses -- a per-IP limit alone would not stop it.',
        );
    }

    public function test_the_per_code_limit_survives_formatting_changes(): void
    {
        // Keyed on the normalised code, so varying case or spacing does not
        // reset the counter.
        $project = Project::factory()->create(['customer_phone' => '081234567890']);
        $code = $project->project_code;

        $variants = [$code, strtolower($code), ' '.$code.' ', strtoupper($code), $code.' '];
        $throttled = false;

        foreach (array_merge($variants, $variants) as $i => $variant) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])
                ->post('/lacak-proyek', [
                    'project_code' => $variant,
                    'verification' => 'salah-'.$i,
                ]);

            if ($response->getStatusCode() === 429) {
                $throttled = true;
                break;
            }

            $this->flushSession();
        }

        $this->assertTrue($throttled, 'Reformatting the code must not reset the per-code limit.');
    }

    public function test_hammering_many_codes_from_one_ip_is_throttled(): void
    {
        $throttled = false;

        for ($i = 1; $i <= 20; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
                ->post('/lacak-proyek', [
                    // A different code each time, so only the IP limit applies.
                    'project_code' => '3DX-'.str_pad((string) $i, 8, 'A', STR_PAD_LEFT),
                    'verification' => '081234567890',
                ]);

            if ($response->getStatusCode() === 429) {
                $throttled = true;
                break;
            }

            $this->flushSession();
        }

        $this->assertTrue($throttled, 'Enumerating codes from one address must be throttled.');
    }

    public function test_a_legitimate_customer_is_not_throttled_by_someone_elses_attempts(): void
    {
        // The per-code limit is keyed on the code, so an attacker working on
        // project A cannot lock out the owner of project B.
        $attacked = Project::factory()->create(['customer_phone' => '081111111111']);
        $mine = Project::factory()->create(['customer_phone' => '082222222222']);

        for ($i = 1; $i <= 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->post('/lacak-proyek', [
                    'project_code' => $attacked->project_code,
                    'verification' => 'salah-'.$i,
                ]);
            $this->flushSession();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post('/lacak-proyek', [
                'project_code' => $mine->project_code,
                'verification' => '082222222222',
            ]);

        $this->assertNotSame(429, $response->getStatusCode());
        $response->assertSee($mine->title);
    }
}
