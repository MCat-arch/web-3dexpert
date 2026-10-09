<?php

namespace App\Support;

use App\Models\Project;

/**
 * The progress-tracker lookup. Decision D2.
 *
 * This is the most exposed surface on the site: unauthenticated, and it reads
 * customer data. A bare `?code=` lookup would let anyone walk 1, 2, 3 and
 * harvest every customer's project name and status -- which is why a second
 * factor the real customer already knows is required.
 *
 * ---------------------------------------------------------------------------
 * THE UNIFORM NEGATIVE
 *
 * find() returns null for all three failure modes:
 *
 *   1. the code does not exist
 *   2. the code exists but the phone/email does not match
 *   3. the code and contact match, but the project is completed or cancelled
 *
 * That is deliberate, and it is why the status filter lives in the same method
 * rather than in the controller. If case 3 produced a distinguishable
 * response -- "this project is already finished" -- the form would confirm
 * that a code is VALID, which is exactly the signal the second factor exists
 * to withhold. An attacker would no longer need the contact details; a
 * different error message would do.
 *
 * So "only projects not yet done" is implemented as a uniform negative, not as
 * a separate message. The caller gets null and renders one response.
 * ---------------------------------------------------------------------------
 */
class ProjectLookup
{
    /** Code body length, mirroring Project::CODE_LENGTH. */
    private const BODY_LENGTH = 8;

    public function find(string $code, string $verification): ?Project
    {
        $code = $this->normaliseCode($code);

        if (blank($code)) {
            return null;
        }

        /*
         * Fetched by code and trackability only -- the contact comparison runs
         * in PHP rather than SQL.
         *
         * Why not one query with an OR on phone/email: the stored phone is
         * normalised and the typed one is not, so a SQL equality test would
         * reject '+62 812...' against '0812...'. Normalising in the query
         * would mean a function on the column, which cannot use the index.
         */
        $project = Project::query()
            ->trackable()
            ->where('project_code', $code)
            ->with('updates')
            ->first();

        if (! $project) {
            return null;
        }

        if (! $project->verificationMatches($verification)) {
            return null;
        }

        return $project;
    }

    /**
     * Accepts what people actually type: lower case, stray spaces, and the
     * '3DX-' prefix either present or missing.
     *
     * Being generous here costs nothing -- the second factor is what provides
     * the security, not the code's formatting.
     */
    public function normaliseCode(string $code): string
    {
        // Strip everything that is not a code character, so spaces, dashes and
        // stray punctuation all collapse. '3DX 7K2M9QX4' and '3dx-7k2m9qx4'
        // become the same string.
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        if (blank($code)) {
            return '';
        }

        /*
         * Tolerate a missing prefix, so '7K2M9QX4' finds '3DX-7K2M9QX4'.
         *
         * The prefix is only stripped when what remains is a full-length body.
         * A naive str_starts_with('3DX') check would corrupt a code whose BODY
         * happens to begin with those characters -- 3, D and X are all in the
         * alphabet, so that is roughly 1 in 22,000 codes, not never.
         */
        if (str_starts_with($code, '3DX') && strlen($code) === self::BODY_LENGTH + 3) {
            $code = substr($code, 3);
        }

        return '3DX-'.$code;
    }
}
