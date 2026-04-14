import { describe, it, expect } from 'vitest'
import { interpolate } from '../interpolate'

describe('interpolate', () => {
    it('returns text unchanged when no placeholders', () => {
        expect(interpolate('https://api.example.com/users', {})).toBe('https://api.example.com/users')
    })

    it('replaces a single resolved variable', () => {
        const vars = { BASE_URL: 'https://api.example.com' }
        expect(interpolate('{{BASE_URL}}/users', vars)).toBe('https://api.example.com/users')
    })

    it('replaces multiple variables in one string', () => {
        const vars = { HOST: 'api.example.com', VERSION: 'v2' }
        expect(interpolate('https://{{HOST}}/{{VERSION}}/users', vars)).toBe('https://api.example.com/v2/users')
    })

    it('leaves unresolved variables as-is', () => {
        expect(interpolate('{{MISSING}}/path', {})).toBe('{{MISSING}}/path')
    })

    it('resolves known variables and preserves unknown ones', () => {
        const vars = { TOKEN: 'abc123' }
        expect(interpolate('Bearer {{TOKEN}} for {{USER}}', vars)).toBe('Bearer abc123 for {{USER}}')
    })

    it('handles empty string', () => {
        expect(interpolate('', { FOO: 'bar' })).toBe('')
    })

    it('handles empty vars map with placeholders present', () => {
        expect(interpolate('{{A}} and {{B}}', {})).toBe('{{A}} and {{B}}')
    })

    it('replaces same variable used multiple times', () => {
        const vars = { ID: '42' }
        expect(interpolate('/users/{{ID}}/posts/{{ID}}', vars)).toBe('/users/42/posts/42')
    })
})
