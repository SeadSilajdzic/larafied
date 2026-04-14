import { describe, expect, it } from 'vitest'
import { evaluateAssertions } from '../utilities/evaluateAssertions'
import type { ProxyResponse } from '../types'

function makeResponse(overrides: Partial<ProxyResponse> = {}): ProxyResponse {
    return {
        status:       200,
        headers:      { 'Content-Type': 'application/json' },
        body:         '{"data":{"id":42,"name":"Alice"}}',
        duration_ms:  50,
        content_type: 'application/json',
        size:         100,
        queries:      [],
        ...overrides,
    }
}

describe('status_equals', () => {
    it('passes when status matches', () => {
        const [r] = evaluateAssertions([{ type: 'status_equals', value: '200' }], makeResponse())
        expect(r.passed).toBe(true)
    })

    it('fails when status does not match', () => {
        const [r] = evaluateAssertions([{ type: 'status_equals', value: '404' }], makeResponse({ status: 200 }))
        expect(r.passed).toBe(false)
        expect(r.message).toContain('404')
    })
})

describe('body_contains', () => {
    it('passes when body contains the string', () => {
        const [r] = evaluateAssertions([{ type: 'body_contains', value: 'Alice' }], makeResponse())
        expect(r.passed).toBe(true)
    })

    it('fails when body does not contain the string', () => {
        const [r] = evaluateAssertions([{ type: 'body_contains', value: 'Bob' }], makeResponse())
        expect(r.passed).toBe(false)
    })
})

describe('json_path_equals', () => {
    it('passes when dot-path value matches', () => {
        const [r] = evaluateAssertions(
            [{ type: 'json_path_equals', key: 'data.name', value: 'Alice' }],
            makeResponse(),
        )
        expect(r.passed).toBe(true)
    })

    it('fails when dot-path value does not match', () => {
        const [r] = evaluateAssertions(
            [{ type: 'json_path_equals', key: 'data.id', value: '99' }],
            makeResponse(),
        )
        expect(r.passed).toBe(false)
        expect(r.message).toContain('42')
    })

    it('fails gracefully when body is not JSON', () => {
        const [r] = evaluateAssertions(
            [{ type: 'json_path_equals', key: 'data.id', value: '1' }],
            makeResponse({ body: 'not json' }),
        )
        expect(r.passed).toBe(false)
        expect(r.message).toContain('JSON')
    })
})

describe('header_equals', () => {
    it('passes with case-insensitive header name match', () => {
        const [r] = evaluateAssertions(
            [{ type: 'header_equals', key: 'content-type', value: 'application/json' }],
            makeResponse(),
        )
        expect(r.passed).toBe(true)
    })

    it('fails when header value does not match', () => {
        const [r] = evaluateAssertions(
            [{ type: 'header_equals', key: 'Content-Type', value: 'text/html' }],
            makeResponse(),
        )
        expect(r.passed).toBe(false)
    })

    it('fails when header is not present', () => {
        const [r] = evaluateAssertions(
            [{ type: 'header_equals', key: 'X-Custom', value: 'foo' }],
            makeResponse(),
        )
        expect(r.passed).toBe(false)
        expect(r.message).toContain('not set')
    })
})

it('evaluates multiple assertions and returns all results', () => {
    const results = evaluateAssertions(
        [
            { type: 'status_equals', value: '200' },
            { type: 'body_contains', value: 'Alice' },
            { type: 'body_contains', value: 'Missing' },
        ],
        makeResponse(),
    )
    expect(results).toHaveLength(3)
    expect(results[0].passed).toBe(true)
    expect(results[1].passed).toBe(true)
    expect(results[2].passed).toBe(false)
})
