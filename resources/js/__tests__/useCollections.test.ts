import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useCollections } from '../composables/useCollections'

const DOM_CONFIG = '{"prefix":"larafied","title":"Larafied","version":"1.0.0"}'

const baseCollection = { id: '01HX', name: 'Auth API', description: null, requests: [], created_at: 0, updated_at: 0 }

const mockResponse = {
    data: [baseCollection],
    meta: { count: 1, limit: 5, limit_reached: false },
}

function setupDom(): void {
    document.body.innerHTML = `<div id="app" data-config='${DOM_CONFIG}'></div>`
    document.head.innerHTML = `<meta name="csrf-token" content="test-token">`
}

function mockFetch(status: number, body: unknown): void {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    }))
}

describe('useCollections', () => {
    beforeEach(() => {
        setupDom()
        vi.restoreAllMocks()
    })

    it('starts with empty state', () => {
        const { collections, loading, limitReached } = useCollections()

        expect(collections.value).toEqual([])
        expect(loading.value).toBe(false)
        expect(limitReached.value).toBe(false)
    })

    it('fetches and populates collections', async () => {
        mockFetch(200, mockResponse)
        const { collections, fetchCollections } = useCollections()

        await fetchCollections()

        expect(collections.value).toHaveLength(1)
        expect(collections.value[0].name).toBe('Auth API')
    })

    it('sets limitReached when meta indicates it', async () => {
        mockFetch(200, { ...mockResponse, meta: { ...mockResponse.meta, limit_reached: true } })
        const { limitReached, fetchCollections } = useCollections()

        await fetchCollections()

        expect(limitReached.value).toBe(true)
    })

    it('removes deleted collection from local state immediately', async () => {
        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce({ ok: true, status: 200, json: () => Promise.resolve(mockResponse) })
            .mockResolvedValueOnce({ ok: true, status: 204, json: () => Promise.resolve(null) }),
        )

        const { collections, fetchCollections, deleteCollection } = useCollections()

        await fetchCollections()
        expect(collections.value).toHaveLength(1)

        await deleteCollection('01HX')
        expect(collections.value).toHaveLength(0)
    })

    it('creates collection and refreshes list', async () => {
        const newCollection = { id: '02HX', name: 'New', description: null, requests: [], created_at: 0, updated_at: 0 }

        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce({ ok: true, status: 201, json: () => Promise.resolve(newCollection) })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ data: [baseCollection, newCollection], meta: { count: 2, limit: 5, limit_reached: false } }),
            }),
        )

        const { collections, createCollection } = useCollections()

        await createCollection('New')

        expect(collections.value).toHaveLength(2)
    })
})
