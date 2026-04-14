import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useHistory } from '../composables/useHistory'

const mockFetch = vi.fn()
vi.stubGlobal('fetch', mockFetch)

// Mirror the data-config attribute that useApi reads
document.body.innerHTML = '<div id="app" data-config=\'{"prefix":"larafied","title":"Larafied","version":"1.0.0"}\'></div>'

function makeResponse(body: unknown, status = 200): Response {
    return {
        ok:     status >= 200 && status < 300,
        status,
        statusText: status === 200 ? 'OK' : 'Error',
        json:   () => Promise.resolve(body),
    } as unknown as Response
}

beforeEach(() => {
    mockFetch.mockReset()
})

describe('useHistory', () => {
    it('fetches history and populates entries', async () => {
        const entry = { id: '1', method: 'GET', url: 'https://example.com', headers: {}, body: null, status: 200, duration_ms: 50, created_at: 1 }
        mockFetch.mockResolvedValue(makeResponse({ data: [entry] }))

        const { entries, fetchHistory } = useHistory()
        await fetchHistory()

        expect(entries.value).toHaveLength(1)
        expect(entries.value[0].method).toBe('GET')
    })

    it('sets locked when server returns upgrade:true (403)', async () => {
        mockFetch.mockResolvedValue(makeResponse({ upgrade: true }, 403))

        const { locked, fetchHistory } = useHistory()
        await fetchHistory()

        expect(locked.value).toBe(true)
    })

    it('clears entries on clearHistory', async () => {
        const entry = { id: '1', method: 'POST', url: 'https://example.com', headers: {}, body: null, status: 201, duration_ms: 30, created_at: 2 }
        mockFetch
            .mockResolvedValueOnce(makeResponse({ data: [entry] }))
            .mockResolvedValueOnce(makeResponse(undefined, 204))

        const { entries, fetchHistory, clearHistory } = useHistory()
        await fetchHistory()
        expect(entries.value).toHaveLength(1)

        await clearHistory()
        expect(entries.value).toHaveLength(0)
    })

    it('prepend adds entry to the front and trims to 50', () => {
        const { entries, prepend } = useHistory()
        entries.value = Array.from({ length: 50 }, (_, i) => ({
            id: String(i), method: 'GET', url: `https://example.com/${i}`,
            headers: {}, body: null, status: 200, duration_ms: 1, created_at: i,
        }))

        prepend({ id: 'new', method: 'DELETE', url: 'https://example.com/new', headers: {}, body: null, status: 204, duration_ms: 5, created_at: 99 })

        expect(entries.value).toHaveLength(50)
        expect(entries.value[0].id).toBe('new')
    })

    it('fails silently on fetch error', async () => {
        mockFetch.mockRejectedValue(new Error('network error'))

        const { entries, fetchHistory } = useHistory()
        await expect(fetchHistory()).resolves.toBeUndefined()
        expect(entries.value).toHaveLength(0)
    })
})
