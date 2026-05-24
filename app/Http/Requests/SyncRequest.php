<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'babies' => ['sometimes', 'array'],
            'babies.*.uuid' => ['required', 'uuid'],
            'babies.*.name' => ['required', 'string', 'max:255'],
            'babies.*.birth_date' => ['required', 'date_format:Y-m-d'],
            'babies.*.gender' => ['nullable', 'string', 'in:male,female'],
            'babies.*.updated_at' => ['required', 'date'],

            'baby_actions' => ['sometimes', 'array'],
            'baby_actions.*.uuid' => ['required', 'uuid'],
            'baby_actions.*.baby_uuid' => ['required', 'uuid'],
            'baby_actions.*.baby_action_type_id' => ['required', 'integer', 'exists:baby_action_types,id'],
            'baby_actions.*.started_at' => ['required', 'date'],
            'baby_actions.*.finished_at' => ['nullable', 'date'],
            'baby_actions.*.reminders' => ['required', 'integer', 'min:0'],
            'baby_actions.*.updated_at' => ['required', 'date'],

            'baby_action_eat_details' => ['sometimes', 'array'],
            'baby_action_eat_details.*.uuid' => ['required', 'uuid'],
            'baby_action_eat_details.*.baby_action_uuid' => ['required', 'uuid'],
            'baby_action_eat_details.*.food_type' => ['nullable', 'string'],
            'baby_action_eat_details.*.breast_side' => ['nullable', 'string', 'in:left,right'],
            'baby_action_eat_details.*.updated_at' => ['required', 'date'],

            'notification_settings' => ['sometimes', 'array'],
            'notification_settings.*.uuid' => ['required', 'uuid'],
            'notification_settings.*.baby_action_type_id' => ['required', 'integer', 'exists:baby_action_types,id'],
            'notification_settings.*.enabled' => ['required', 'boolean'],
            'notification_settings.*.notify_after_minutes' => ['required', 'integer', 'min:1'],
            'notification_settings.*.notify_from' => ['required', 'string', 'in:started_at,finished_at'],
            'notification_settings.*.updated_at' => ['required', 'date'],
        ];
    }
}
