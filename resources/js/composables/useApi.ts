import type { LarafiedConfig } from '../types'

function getConfig(): LarafiedConfig {
    const el = document.getElementById('app')
    const raw = el?.getAttribute('data-config')
    return raw ? (JSON.parse(raw) as LarafiedConfig) : { prefix: 'larafied', title: 'Larafied', version: '1.0.0' }
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function baseUrl(): string {
    const { prefix } = getConfig()
    return `/${prefix}/api`
}

async function request<T>(method: string, path: string, data?: unknown): Promise<T> {
    const response = await fetch(`${baseUrl()}${path}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data !== undefined ? JSON.stringify(data) : undefined,
    })

    if (!response.ok && response.status !== 422) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    return response.json() as Promise<T>
}

export function useApi() {
    return {
        get:  <T>(path: string)                     => request<T>('GET',    path),
        post: <T>(path: string, data?: unknown)     => request<T>('POST',   path, data),
        put:  <T>(path: string, data?: unknown)     => request<T>('PUT',    path, data),
        del:  <T>(path: string)                     => request<T>('DELETE', path),
    }
}
