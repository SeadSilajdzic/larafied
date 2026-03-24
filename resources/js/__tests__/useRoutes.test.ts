import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useRoutes } from '../composables/useRoutes'

const DOM_CONFIG = '{"prefix":"larafied","title":"Larafied","version":"1.0.0"}'

const mockGroups = [
    {
        group: 'api',
        routes: [
            {
                methods: ['GET'],
                uri: 'api/users',
                name: 'users.index',
                middleware: [],
                group: 'api',
                parameters: [],
                action: 'UserController@index',
            },
        ],
    },
]

function setupDom(): void {
    document.body.innerHTML = `<div id="app" data-config='${DOM_CONFIG}'></div>`
    document.head.innerHTML = `<meta name="csrf-token" content="test-token">`
}

describe('useRoutes', () => {
    beforeEach(() => {
        setupDom()
        vi.restoreAllMocks()
    })

    it('starts with empty groups and loading false', () => {
        const { groups, loading } = useRoutes()

        expect(groups.value).toEqual([])
        expect(loading.value).toBe(false)
    })

    it('fetches groups from /routes and populates state', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: () => Promise.resolve(mockGroups),
        }))

        const { groups, loading, fetchRoutes } = useRoutes()

        await fetchRoutes()

        expect(groups.value).toEqual(mockGroups)
        expect(loading.value).toBe(false)
    })

    it('sets loading to true while fetching, false after', async () => {
        vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})))

        const { loading, fetchRoutes } = useRoutes()
        fetchRoutes()

        expect(loading.value).toBe(true)
    })

    it('sets error message on fetch failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')))

        const { error, fetchRoutes } = useRoutes()

        await fetchRoutes()

        expect(error.value).toBe('Network error')
    })

    it('clears error on subsequent successful fetch', async () => {
        vi.stubGlobal('fetch', vi.fn()
            .mockRejectedValueOnce(new Error('Network error'))
            .mockResolvedValueOnce({ ok: true, status: 200, json: () => Promise.resolve(mockGroups) }),
        )

        const { error, fetchRoutes } = useRoutes()

        await fetchRoutes()
        expect(error.value).toBe('Network error')

        await fetchRoutes()
        expect(error.value).toBeNull()
    })
})
