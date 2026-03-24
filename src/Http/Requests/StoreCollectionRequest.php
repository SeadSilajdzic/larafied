<?php

declare(strict_types=1);

namespace Larafied\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
