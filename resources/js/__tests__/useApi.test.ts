import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useApi } from '../composables/useApi'

const DOM_CONFIG = '{"prefix":"larafied","title":"Larafied","version":"1.0.0"}'

function setupDom(): void {
    document.body.innerHTML = `<div id="app" data-config='${DOM_CONFIG}'></div>`
    document.head.innerHTML = `<meta name="csrf-token" content="test-token">`
}

function mockFetch(status: number, body: unknown): void {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
        ok: status >= 200 && status < 300,
        status,
        statusText: status === 200 ? 'OK' : String(status),
        json: () => Promise.resolve(body),
    }))
}

describe('useApi', () => {
    beforeEach(() => {
        setupDom()
        vi.restoreAllMocks()
    })

    it('makes GET request to prefixed URL with required headers', async () => {
        mockFetch(200, { data: [] })
        const api = useApi()

        await api.get('/routes')

        expect(vi.mocked(fetch)).toHaveBeenCalledWith(
            '/larafied/api/routes',
            expect.objectContaining({
                method: 'GET',
                headers: expect.objectContaining({
                    'X-CSRF-TOKEN': 'test-token',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }),
            }),
        )
    })

    it('makes POST request with serialized JSON body', async () => {
        mockFetch(201, { id: '1', name: 'Test' })
        const api = useApi()

        await api.post('/collections', { name: 'Test' })

        expect(vi.mocked(fetch)).toHaveBeenCalledWith(
            '/larafied/api/collections',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ name: 'Test' }),
            }),
        )
    })

    it('makes DELETE request without body', async () => {
        mockFetch(204, null)
        const api = useApi()

        await api.del('/collections/abc')

        expect(vi.mocked(fetch)).toHaveBeenCalledWith(
            '/larafied/api/collections/abc',
            expect.objectContaining({ method: 'DELETE' }),
        )
    })

    it('throws on 5xx error responses', async () => {
        mockFetch(500, {})
        const api = useApi()

        await expect(api.get('/routes')).rejects.toThrow('HTTP 500')
    })

    it('does not throw on 422 — returns parsed JSON', async () => {
        mockFetch(422, { message: 'Validation error', upgrade: true })
        const api = useApi()

        const result = await api.post('/collections', { name: '' })

        expect(result).toEqual({ message: 'Validation error', upgrade: true })
    })
})
