/**
 * Interpolation integration tests.
 *
 * These tests cover the full interpolation flow as wired in App.vue:
 *   1. Building the envVars map from an active environment's variables array
 *   2. Resolving {{VAR}} placeholders in the request URL
 *   3. Resolving {{VAR}} placeholders in header values (skipping disabled/empty headers)
 *   4. Edge cases: no active environment, partial resolution, secret variables
 */
import { describe, it, expect } from 'vitest'
import { interpolate } from '../interpolate'
import type { EnvVariable, HeaderRow } from '../types'

/** Replicates the envVars computed in App.vue */
function buildEnvVars(variables: EnvVariable[]): Record<string, string> {
    return Object.fromEntries(variables.map(v => [v.key, v.value]))
}

/** Replicates buildHeaders() in App.vue */
function buildHeaders(headers: HeaderRow[], envVars: Record<string, string>): Record<string, string> {
    const result: Record<string, string> = {}
    for (const h of headers) {
        if (h.enabled && h.key.trim()) {
            result[h.key.trim()] = interpolate(h.value, envVars)
        }
    }
    return result
}

describe('interpolation integration', () => {
    describe('envVars construction from active environment', () => {
        it('builds a flat key→value map from the variables array', () => {
            const variables: EnvVariable[] = [
                { key: 'BASE_URL', value: 'https://api.example.com' },
                { key: 'TOKEN', value: 'secret123' },
            ]
            expect(buildEnvVars(variables)).toEqual({
                BASE_URL: 'https://api.example.com',
                TOKEN: 'secret123',
            })
        })

        it('returns an empty map when there are no variables', () => {
            expect(buildEnvVars([])).toEqual({})
        })

        it('includes secret variables in the map (value is always resolved)', () => {
            const variables: EnvVariable[] = [
                { key: 'API_KEY', value: 'sk-live-xyz', secret: true },
            ]
            expect(buildEnvVars(variables)).toEqual({ API_KEY: 'sk-live-xyz' })
        })
    })

    describe('URL interpolation', () => {
        it('resolves a single variable in the URL', () => {
            const envVars = buildEnvVars([{ key: 'BASE_URL', value: 'https://api.example.com' }])
            expect(interpolate('{{BASE_URL}}/users', envVars)).toBe('https://api.example.com/users')
        })

        it('resolves multiple variables in one URL', () => {
            const envVars = buildEnvVars([
                { key: 'HOST', value: 'api.example.com' },
                { key: 'VERSION', value: 'v2' },
            ])
            expect(interpolate('https://{{HOST}}/api/{{VERSION}}/users', envVars)).toBe(
                'https://api.example.com/api/v2/users',
            )
        })

        it('preserves unresolved placeholders when no env is active', () => {
            const envVars = buildEnvVars([])
            expect(interpolate('{{BASE_URL}}/api/users', envVars)).toBe('{{BASE_URL}}/api/users')
        })

        it('resolves known variables and preserves unknown ones in the same URL', () => {
            const envVars = buildEnvVars([{ key: 'BASE_URL', value: 'https://api.example.com' }])
            expect(interpolate('{{BASE_URL}}/{{MISSING}}/users', envVars)).toBe(
                'https://api.example.com/{{MISSING}}/users',
            )
        })

        it('passes through a plain URL with no placeholders unchanged', () => {
            const envVars = buildEnvVars([{ key: 'BASE_URL', value: 'https://api.example.com' }])
            expect(interpolate('/api/users', envVars)).toBe('/api/users')
        })
    })

    describe('header interpolation', () => {
        it('resolves variables in header values', () => {
            const envVars = buildEnvVars([{ key: 'TOKEN', value: 'bearer-xyz' }])
            const headers: HeaderRow[] = [
                { key: 'Authorization', value: 'Bearer {{TOKEN}}', enabled: true },
            ]
            expect(buildHeaders(headers, envVars)).toEqual({ Authorization: 'Bearer bearer-xyz' })
        })

        it('skips disabled headers', () => {
            const envVars = buildEnvVars([{ key: 'TOKEN', value: 'abc' }])
            const headers: HeaderRow[] = [
                { key: 'Authorization', value: 'Bearer {{TOKEN}}', enabled: false },
                { key: 'Accept', value: 'application/json', enabled: true },
            ]
            expect(buildHeaders(headers, envVars)).toEqual({ Accept: 'application/json' })
        })

        it('skips headers with an empty key', () => {
            const envVars = buildEnvVars([])
            const headers: HeaderRow[] = [
                { key: '', value: 'something', enabled: true },
                { key: '   ', value: 'something', enabled: true },
                { key: 'X-App', value: 'larafied', enabled: true },
            ]
            expect(buildHeaders(headers, envVars)).toEqual({ 'X-App': 'larafied' })
        })

        it('trims whitespace from header keys', () => {
            const envVars = buildEnvVars([])
            const headers: HeaderRow[] = [
                { key: '  Content-Type  ', value: 'application/json', enabled: true },
            ]
            expect(buildHeaders(headers, envVars)).toEqual({ 'Content-Type': 'application/json' })
        })

        it('returns empty object when all headers are disabled or empty', () => {
            const envVars = buildEnvVars([])
            const headers: HeaderRow[] = [
                { key: 'Authorization', value: 'Bearer token', enabled: false },
                { key: '', value: 'ignored', enabled: true },
            ]
            expect(buildHeaders(headers, envVars)).toEqual({})
        })
    })

    describe('full send-flow simulation', () => {
        it('resolves URL and headers together using the same envVars', () => {
            const variables: EnvVariable[] = [
                { key: 'HOST', value: 'myapp.test' },
                { key: 'TOKEN', value: 'abc-123' },
            ]
            const envVars = buildEnvVars(variables)

            const url = '/api/{{HOST}}/users'
            const headers: HeaderRow[] = [
                { key: 'Authorization', value: 'Bearer {{TOKEN}}', enabled: true },
                { key: 'X-Tenant', value: '{{HOST}}', enabled: true },
            ]

            expect(interpolate(url, envVars)).toBe('/api/myapp.test/users')
            expect(buildHeaders(headers, envVars)).toEqual({
                Authorization: 'Bearer abc-123',
                'X-Tenant': 'myapp.test',
            })
        })

        it('handles an environment with no matching variables gracefully', () => {
            const variables: EnvVariable[] = [{ key: 'OTHER', value: 'irrelevant' }]
            const envVars = buildEnvVars(variables)

            expect(interpolate('{{BASE_URL}}/api/users', envVars)).toBe('{{BASE_URL}}/api/users')
            expect(buildHeaders(
                [{ key: 'Authorization', value: 'Bearer {{TOKEN}}', enabled: true }],
                envVars,
            )).toEqual({ Authorization: 'Bearer {{TOKEN}}' })
        })
    })
})
