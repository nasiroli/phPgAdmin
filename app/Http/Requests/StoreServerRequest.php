<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerRequest extends FormRequest
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
            'name'  => ['required', 'string', 'max:255'],
            'host'  => ['required', 'string', 'max:255'],
            'port'  => ['required', 'integer', 'min:1', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
