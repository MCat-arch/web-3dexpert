<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Support\PhoneNumber;
use App\Support\ProjectLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_code_is_not_derived_from_the_primary_key(): void
    {
        /*
         * A derived code would make the tracker an enumeration oracle: type
         * 1, 2, 3 and read every customer's project.
         *
         * Asserted as unpredictability rather than "does not contain the id" --
         * an earlier draft did the latter and failed spuriously, because 2, 3,
         * 4, 6, 7 and 8 are all in the code alphabet, so a body legitimately
         * contains the id's digits now and then.
         *
         * A derived or sequential scheme would produce codes that sort in the
         * same order as the ids. Random ones will not, with 20 samples.
         */
        $codes = Project::factory()->count(20)->create()
            ->sortBy('id')
            ->pluck('project_code')
            ->values()
            ->all();

        $sorted = $codes;
        sort($sorted);

        $this->assertNotSame($sorted, $codes, 'Codes must not order with the primary key.');

        foreach ($codes as $code) {
            $this->assertSame(12, strlen($code), 'Code length must not vary with the id.');
        }
    }

    public function test_codes_are_unique_across_many_projects(): void
    {
        $codes = Project::factory()->count(50)->create()->pluck('project_code');

        $this->assertCount(50, $codes->unique());
    }

    public function test_the_code_avoids_characters_people_misread(): void
    {
        // Customers read these off a message and type them by hand. 0/O, 1/I/L
        // and 5/S are omitted, because a code that cannot be transcribed
        // reliably produces support load indistinguishable from a real fault.
        foreach (range(1, 30) as $i) {
            $body = str_replace('3DX-', '', Project::generateCode());

            $this->assertSame(
                0,
                preg_match('/[01OIL5S]/', $body),
                "Generated code [{$body}] contains an ambiguous character.",
            );
        }
    }

    public function test_the_code_has_a_stable_shape(): void
    {
        $this->assertMatchesRegularExpression('/^3DX-[234678A-Z]{8}$/', Project::generateCode());
    }

    public function test_a_supplied_code_is_kept(): void
    {
        $project = Project::factory()->create(['project_code' => '3DX-MANUAL24']);

        $this->assertSame('3DX-MANUAL24', $project->project_code);
    }

    public function test_the_lookup_normalises_what_people_actually_type(): void
    {
        $lookup = new ProjectLookup;

        foreach ([
            '3DX-7K2M9QX4' => '3DX-7K2M9QX4',
            '3dx-7k2m9qx4' => '3DX-7K2M9QX4',
            ' 3DX-7K2M9QX4 ' => '3DX-7K2M9QX4',
            '3DX 7K2M9QX4' => '3DX-7K2M9QX4',
            '7K2M9QX4' => '3DX-7K2M9QX4',
            '7k2m9qx4' => '3DX-7K2M9QX4',
        ] as $typed => $expected) {
            $this->assertSame($expected, $lookup->normaliseCode($typed), "input: {$typed}");
        }
    }

    public function test_phone_normalisation_is_shared_with_registration(): void
    {
        // P1 stores a customer's phone through this same helper. If the two
        // ever diverged, a real customer would be told their own project code
        // is invalid -- and it would look like a data problem.
        foreach ([
            '+62 812-3456-7890',
            '62 812 3456 7890',
            '0812-3456-7890',
            '081234567890',
            '(0812) 3456 7890',
        ] as $typed) {
            $this->assertSame('081234567890', PhoneNumber::normalise($typed), "input: {$typed}");
        }
    }

    public function test_phone_matching_rejects_nulls_rather_than_treating_them_as_equal(): void
    {
        // Two projects with no phone must not match each other, and a blank
        // input must not match a blank column.
        $this->assertFalse(PhoneNumber::matches(null, null));
        $this->assertFalse(PhoneNumber::matches('', ''));
        $this->assertFalse(PhoneNumber::matches('081234567890', null));
        $this->assertFalse(PhoneNumber::matches(null, '081234567890'));
        $this->assertTrue(PhoneNumber::matches('081234567890', '+62 812 3456 7890'));
    }
}
