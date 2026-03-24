<?php

declare(strict_types=1);

namespace Larafied\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSavedRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'data'            => ['required', 'array'],
            'data.method'     => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'data.url'        => ['required', 'string', 'max:2048'],
            'data.headers'    => ['nullable', 'array'],
            'data.body'       => ['nullable'],
            'data.query'      => ['nullable', 'array'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ];
    }
}
