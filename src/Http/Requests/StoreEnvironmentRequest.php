<?php

declare(strict_types=1);

namespace Larafied\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'variables'           => ['nullable', 'array'],
            'variables.*.key'     => ['required', 'string', 'max:255'],
            'variables.*.value'   => ['required', 'string'],
            'variables.*.secret'  => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }
}
