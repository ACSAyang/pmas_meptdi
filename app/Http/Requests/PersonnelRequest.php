<?php

namespace App\Http\Requests;

use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('personnel.manage');
    }

    public function rules(): array
    {
        $personnel = $this->route('personnel');
        $creating = ! $personnel;

        return [
            'service_number' => ['required', 'string', 'max:30', Rule::unique('personnel', 'service_number')->ignore($personnel?->id)],
            'rank_id' => ['required', 'exists:ranks,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
            'designation' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'service_status' => ['required', Rule::in(array_keys(Personnel::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            // initial assignment (create only)
            'unit_id' => [$creating ? 'required' : 'nullable', 'exists:units,id'],
            'assignment_type' => [$creating ? 'required' : 'nullable', Rule::in(array_keys(PersonnelAssignment::TYPES))],
            'start_date' => [$creating ? 'required' : 'nullable', 'date'],
            'order_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['rank_id' => 'rank', 'unit_id' => 'unit'];
    }
}
