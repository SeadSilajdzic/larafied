import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useLicense } from '../composables/useLicense'

const mockFetch = vi.fn()
vi.stubGlobal('fetch', mockFetch)

function mockResponse(data: unknown, status = 200) {
    return Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(data),
    })
}

beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = '<div id="app" data-config=\'{"prefix":"larafied","title":"Larafied","version":"1.0.0"}\'></div>'
})

describe('useLicense', () => {
    it('initialises with free tier', () => {
        const { tier, features, graceWarning } = useLicense()

        expect(tier.value).toBe('free')
        expect(features.value).toEqual([])
        expect(graceWarning.value).toBe(false)
    })

    it('fetches and sets tier and features', async () => {
        mockFetch.mockReturnValueOnce(mockResponse({
            tier:          'pro',
            features:      ['unlimited_collections', 'environments'],
            grace_warning: false,
            validated_at:  '2026-03-24T12:00:00+00:00',
            grace_until:   null,
        }))

        const { tier, features, graceWarning, fetchLicense } = useLicense()
        await fetchLicense()

        expect(tier.value).toBe('pro')
        expect(features.value).toContain('unlimited_collections')
        expect(graceWarning.value).toBe(false)
    })

    it('sets graceWarning when api returns grace_warning true', async () => {
        mockFetch.mockReturnValueOnce(mockResponse({
            tier:          'pro',
            features:      ['unlimited_collections'],
            grace_warning: true,
            validated_at:  '2026-03-20T12:00:00+00:00',
            grace_until:   '2026-03-25T12:00:00+00:00',
        }))

        const { graceWarning, fetchLicense } = useLicense()
        await fetchLicense()

        expect(graceWarning.value).toBe(true)
    })

    it('activates a license key and updates tier', async () => {
        mockFetch.mockReturnValueOnce(mockResponse({
            tier:          'pro',
            features:      ['unlimited_collections'],
            validated_at:  '2026-03-24T12:00:00+00:00',
            grace_until:   null,
        }))

        const { tier, activate } = useLicense()
        await activate('AW-TEST-1234', 'myapp.local')

        expect(tier.value).toBe('pro')
    })

    it('sets error when activation fails', async () => {
        mockFetch.mockReturnValueOnce(mockResponse({
            message: 'License activation failed.',
            reason:  'license_revoked',
        }, 422))

        const { error, activate } = useLicense()
        await activate('AW-BAD-KEY', 'myapp.local')

        expect(error.value).toContain('license_revoked')
    })
})
