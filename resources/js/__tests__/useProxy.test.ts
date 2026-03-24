import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProxy } from '../composables/useProxy'

const DOM_CONFIG = '{"prefix":"larafied","title":"Larafied","version":"1.0.0"}'

const mockProxyResponse = {
    status: 200,
    headers: { 'content-type': 'application/json' },
    body: '{"ok":true}',
    duration_ms: 42,
    content_type: 'application/json',
    size: 10,
}

function setupDom(): void {
    document.body.innerHTML = `<div id="app" data-config='${DOM_CONFIG}'></div>`
    document.head.innerHTML = `<meta name="csrf-token" content="test-token">`
}

describe('useProxy', () => {
    beforeEach(() => {
        setupDom()
        vi.restoreAllMocks()
    })

    it('starts with null response and sending false', () => {
        const { response, sending, error } = useProxy()

        expect(response.value).toBeNull()
        expect(sending.value).toBe(false)
        expect(error.value).toBeNull()
    })

    it('sends request and populates response on success', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: () => Promise.resolve(mockProxyResponse),
        }))

        const { response, sending, send } = useProxy()

        await send({ method: 'GET', url: 'https://example.com/api' })

        expect(response.value).toEqual(mockProxyResponse)
        expect(sending.value).toBe(false)
    })

    it('sets error when proxy returns error field (SSRF block)', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 422,
            json: () => Promise.resolve({ error: 'Requests to private IP ranges are not allowed.', type: 'ssrf' }),
        }))

        const { error, response, send } = useProxy()

        await send({ method: 'GET', url: 'http://192.168.1.1/' })

        expect(error.value).toBe('Requests to private IP ranges are not allowed.')
        expect(response.value).toBeNull()
    })

    it('sets error on network failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Failed to fetch')))

        const { error, send } = useProxy()

        await send({ method: 'GET', url: 'https://example.com' })

        expect(error.value).toBe('Failed to fetch')
    })

    it('clears previous response before new send', async () => {
        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce({ ok: true, status: 200, json: () => Promise.resolve(mockProxyResponse) })
            .mockResolvedValueOnce({ ok: false, status: 422, json: () => Promise.resolve({ error: 'blocked', type: 'ssrf' }) }),
        )

        const { response, error, send } = useProxy()

        await send({ method: 'GET', url: 'https://example.com' })
        expect(response.value).not.toBeNull()

        await send({ method: 'GET', url: 'http://192.168.1.1' })
        expect(response.value).toBeNull()
        expect(error.value).toBe('blocked')
    })
})
