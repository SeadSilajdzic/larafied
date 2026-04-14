import { describe, it, expect } from 'vitest'
import { runPreRequestScript, type PreRequestContext } from '../preRequest'

function makeContext(overrides: Partial<PreRequestContext> = {}): PreRequestContext {
    return {
        method:  'GET',
        url:     'https://api.example.com/users',
        headers: {},
        body:    '',
        envVars: {},
        ...overrides,
    }
}

describe('runPreRequestScript', () => {
    it('returns context unchanged when script is empty', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('', ctx)
        expect(result.url).toBe(ctx.url)
        expect(result.error).toBeUndefined()
    })

    it('returns context unchanged when script is only whitespace', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('   \n  ', ctx)
        expect(result.url).toBe(ctx.url)
    })

    it('allows script to set request URL via pm.request.url', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('pm.request.url = "https://other.com/api"', ctx)
        expect(result.url).toBe('https://other.com/api')
    })

    it('allows script to set a request header', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('pm.request.headers["X-Custom"] = "value"', ctx)
        expect(result.headers['X-Custom']).toBe('value')
    })

    it('allows script to read an environment variable', () => {
        const ctx = makeContext({ envVars: { TOKEN: 'abc123' } })
        const result = runPreRequestScript(
            'pm.request.headers["Authorization"] = "Bearer " + pm.environment.get("TOKEN")',
            ctx,
        )
        expect(result.headers['Authorization']).toBe('Bearer abc123')
    })

    it('returns empty string for unresolved environment variable', () => {
        const ctx = makeContext({ envVars: {} })
        const result = runPreRequestScript(
            'pm.request.headers["X-Token"] = pm.environment.get("MISSING")',
            ctx,
        )
        expect(result.headers['X-Token']).toBe('')
    })

    it('allows script to set an environment variable', () => {
        const ctx = makeContext()
        const result = runPreRequestScript(
            'pm.environment.set("DYNAMIC", "set-by-script")',
            ctx,
        )
        expect(result.envVars['DYNAMIC']).toBe('set-by-script')
    })

    it('allows script to set request method', () => {
        const ctx = makeContext({ method: 'GET' })
        const result = runPreRequestScript('pm.request.method = "POST"', ctx)
        expect(result.method).toBe('POST')
    })

    it('allows script to set request body', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('pm.request.body = JSON.stringify({ key: "val" })', ctx)
        expect(result.body).toBe('{"key":"val"}')
    })

    it('captures syntax errors and returns them in result.error', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('this is invalid ===== js', ctx)
        expect(result.error).toBeDefined()
        expect(result.error).toContain('Script error')
    })

    it('captures runtime errors and returns them in result.error', () => {
        const ctx = makeContext()
        const result = runPreRequestScript('throw new Error("boom")', ctx)
        expect(result.error).toContain('boom')
    })

    it('does not mutate the original context', () => {
        const ctx = makeContext({ url: 'https://original.com' })
        runPreRequestScript('pm.request.url = "https://mutated.com"', ctx)
        expect(ctx.url).toBe('https://original.com')
    })

    it('script changes to headers are additive — original headers preserved', () => {
        const ctx = makeContext({ headers: { Accept: 'application/json' } })
        const result = runPreRequestScript('pm.request.headers["X-New"] = "yes"', ctx)
        expect(result.headers['Accept']).toBe('application/json')
        expect(result.headers['X-New']).toBe('yes')
    })
})
