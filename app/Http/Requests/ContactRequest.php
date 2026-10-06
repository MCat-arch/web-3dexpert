<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Legacy had NO validation layer anywhere: $_POST['judul'], $_POST['price'],
 * $_POST['id'] went straight into queries with no type, length or required
 * checks -- and in the update branch $_POST['id'] was not even cast to int,
 * although the delete branch did cast it.
 *
 * A FormRequest runs before the controller action, so an invalid payload never
 * reaches the model.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],

            // Bots fill every field they find; humans never see this one.
            'honeypot' => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'honeypot.prohibited' => __('contact.spam_detected'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('contact.name'),
            'email' => __('contact.email'),
            'phone' => __('contact.phone'),
            'subject' => __('contact.subject'),
            'message' => __('contact.message'),
            'service_id' => __('contact.service'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Crude but effective against the most common spam shape:
                // a message that is mostly links.
                $linkCount = preg_match_all('#https?://#i', (string) $this->input('message'));

                if ($linkCount > 3) {
                    $validator->errors()->add('message', __('contact.too_many_links'));
                }
            },
        ];
    }
}
