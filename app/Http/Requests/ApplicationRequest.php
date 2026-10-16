<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership / on-behalf rights are checked in the controller & policy
    }

    public function rules(): array
    {
        return [
            'personnel_id' => ['nullable', 'integer', 'exists:personnel,id'],
            'application_type_id' => ['required', 'integer', 'exists:application_types,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'purpose' => ['required', 'string', 'min:3', 'max:2000'],
            'destination' => ['nullable', 'string', 'max:200'],
            'contact_while_away' => ['nullable', 'string', 'max:100'],
            'late_reason' => ['nullable', 'string', 'max:1000'],
            'intent' => ['nullable', 'in:draft,submit'],
        ];
    }

    public function attributes(): array
    {
        return [
            'application_type_id' => 'application type',
            'start_at' => 'departure date/time',
            'end_at' => 'return date/time',
            'late_reason' => 'reason for late / retrospective request',
        ];
    }

    public function messages(): array
    {
        return ['end_at.after' => 'The return date/time must be after the departure date/time (BR-005).'];
    }
}
