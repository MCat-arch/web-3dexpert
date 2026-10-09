<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ProjectLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * SHAPE ONLY. Never existence.
     *
     * A rule like `exists:projects,project_code` would turn this form into the
     * exact oracle D2 exists to remove: "code not found" as a validation error
     * confirms which codes are real, and no second factor would be needed to
     * enumerate them.
     *
     * So the only job here is to reject obvious junk before it reaches the
     * database. Whether the code exists, whether the contact matches, and
     * whether the project is still open are all answered by one uniform
     * response in the controller.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_code' => ['required', 'string', 'max:32'],
            // Phone or email; the lookup accepts either.
            'verification' => ['required', 'string', 'max:191'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_code' => __('tracker.code'),
            'verification' => __('tracker.verification'),
        ];
    }
}
