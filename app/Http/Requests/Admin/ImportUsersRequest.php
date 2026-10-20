<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to import.',
            'file.mimes' => 'The file must be a CSV file (.csv). In Excel, use File → Save As → CSV UTF-8.',
            'file.max' => 'The file is larger than 2 MB. Split it into smaller files.',
        ];
    }
}
