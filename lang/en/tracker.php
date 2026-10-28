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
    'form_title' => 'Check your project progress',
    'form_intro' => 'Enter the project code and one of the registered contacts. Both must match.',

    // ONE message for all three failure modes: unknown code, mismatched
    // contact, and cancelled or hidden. Do not split these -- a "project was
    // cancelled" message confirms the code is VALID, which is the signal the
    // second factor exists to withhold.
    'not_found' => 'No project found. Please check the project code and your contact details, or get in touch.',

    'result_title' => 'Project Progress',
    'project_title' => 'Project',
    'current_stage' => 'Current stage',
    'status' => 'Status',
    'started_at' => 'Started',
    'estimated_completion' => 'Estimated completion',
    'date_completed' => 'Completed',
    'dates' => 'Dates',
    'timeline' => 'Progress History',
    'no_updates' => 'No progress notes yet.',

    'overall_progress' => 'Overall Progress',
    'attachments' => 'Progress Files',
    'attachments_note' => 'The latest files from the team for you to review.',
    'download' => 'Download',

    'final_heading' => 'Final Deliverables',
    'final_completed_on' => 'Completed on :date',
    'final_default_note' => 'This project is complete. The final files are available below.',
    'final_no_files' => 'No final files have been uploaded yet. Contact the admin if you need them.',

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
