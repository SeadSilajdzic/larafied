<?php

declare(strict_types=1);

namespace Larafied\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActivateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'    => ['required', 'string'],
            'domain' => ['nullable', 'string'],
        ];
    }
}
