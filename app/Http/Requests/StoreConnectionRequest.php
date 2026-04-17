<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectionRequest extends FormRequest
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
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'database'  => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:255'],
            'password'  => ['required', 'string', 'max:2000'],
            'sslmode'   => ['required', Rule::in(['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'])],
        ];
    }
}
