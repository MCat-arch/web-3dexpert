<?php

return [
    'eyebrow' => 'Track Project',
    'title' => 'Track Project Progress',
    'meta_description' => 'Enter your project code and your WhatsApp number or email to see the latest progress.',

    'code' => 'Project code',
    'code_hint' => 'For example: 3DX-7K2M9QX4. We send this when your project starts.',
    'verification' => 'WhatsApp number or email',
    'verification_hint' => 'Use the contact details registered on this project.',
    'submit' => 'Track',

    // ONE message for all three failure modes: unknown code, mismatched
    // contact, and already completed or cancelled. Do not split these -- a
    // "project already finished" message confirms the code is VALID, which is
    // the signal the second factor exists to withhold.
    'not_found' => 'No project found. Please check the project code and your contact details, or get in touch.',

    'result_title' => 'Project Progress',
    'project_title' => 'Project',
    'current_stage' => 'Current stage',
    'status' => 'Status',
    'started_at' => 'Started',
    'estimated_completion' => 'Estimated completion',
    'timeline' => 'Progress History',
    'no_updates' => 'No progress notes yet.',

    'status_in_progress' => 'In progress',
    'status_on_hold' => 'On hold',
    'status_completed' => 'Completed',
    'status_cancelled' => 'Cancelled',

    'update_pending' => 'Not started',
    'update_in_progress' => 'In progress',
    'update_done' => 'Done',

    'help' => 'Cannot find your project?',
    'help_cta' => 'Contact us on WhatsApp',
];
