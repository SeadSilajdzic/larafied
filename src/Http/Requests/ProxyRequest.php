<?php

declare(strict_types=1);

namespace Larafied\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProxyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method'      => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'url'         => ['required', 'string', 'url:http,https'],
            'headers'     => ['nullable', 'array'],
            'body'        => ['nullable'],
            'debug'       => ['nullable', 'boolean'],
            'auth'        => ['nullable', 'array'],
            'auth.type'   => ['required_with:auth', 'string', 'in:none,bearer,basic,apikey'],
            'auth.token'  => ['nullable', 'string'],
            'auth.username' => ['nullable', 'string'],
            'auth.password' => ['nullable', 'string'],
            'auth.key'    => ['nullable', 'string'],
            'auth.value'  => ['nullable', 'string'],
            'auth.in'     => ['nullable', 'string', 'in:header,query'],
        ];
    }
}
