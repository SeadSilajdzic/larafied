import { describe, it, expect } from 'vitest'
import { parsePostmanCollection, exportToPostman } from '../postman'
import type { Collection } from '../types'

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const BASIC_COLLECTION = {
    info: {
        name:   'My API',
        schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    item: [
        {
            name: 'Get Users',
            request: {
                method: 'GET',
                header: [{ key: 'Accept', value: 'application/json' }],
                url:    { raw: 'https://api.example.com/users' },
            },
        },
    ],
}

const COLLECTION_WITH_FOLDER = {
    info: {
        name:   'Organised API',
        schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    item: [
        {
            name: 'Auth',
            item: [
                {
                    name: 'Login',
                    request: {
                        method: 'POST',
                        header: [],
                        url:    { raw: '/api/login' },
                        body:   { mode: 'raw', raw: '{"email":"a@b.com"}' },
                    },
                },
            ],
        },
        {
            name: 'Top-level request',
            request: {
                method: 'DELETE',
                header: [],
                url:    { raw: '/api/resource/1' },
            },
        },
    ],
}

// ─── parsePostmanCollection ────────────────────────────────────────────────────

describe('parsePostmanCollection', () => {
    it('parses a basic collection with one top-level request', () => {
        const result = parsePostmanCollection(BASIC_COLLECTION)
        expect(result.name).toBe('My API')
        expect(result.folders).toHaveLength(0)
        expect(result.requests).toHaveLength(1)

        const req = result.requests[0]
        expect(req.name).toBe('Get Users')
        expect(req.data.method).toBe('GET')
        expect(req.data.url).toBe('https://api.example.com/users')
        expect(req.data.headers).toEqual({ Accept: 'application/json' })
    })

    it('parses folders and top-level requests separately', () => {
        const result = parsePostmanCollection(COLLECTION_WITH_FOLDER)
        expect(result.name).toBe('Organised API')
        expect(result.folders).toHaveLength(1)
        expect(result.requests).toHaveLength(1)

        const folder = result.folders[0]
        expect(folder.name).toBe('Auth')
        expect(folder.requests).toHaveLength(1)
        expect(folder.requests[0].data.method).toBe('POST')
        expect(folder.requests[0].data.body).toBe('{"email":"a@b.com"}')

        expect(result.requests[0].data.method).toBe('DELETE')
    })

    it('accepts a URL as a plain string', () => {
        const col = {
            info: {
                name: 'String URL Test',
                schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            },
            item: [{
                name: 'Req',
                request: { method: 'GET', url: 'https://plain-string.com/path' },
            }],
        }
        const result = parsePostmanCollection(col)
        expect(result.requests[0].data.url).toBe('https://plain-string.com/path')
    })

    it('ignores disabled headers', () => {
        const col = {
            info: {
                name:   'Disabled Headers',
                schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            },
            item: [{
                name: 'Req',
                request: {
                    method: 'GET',
                    header: [
                        { key: 'X-Active', value: 'yes', disabled: false },
                        { key: 'X-Disabled', value: 'no', disabled: true },
                    ],
                    url: { raw: '/test' },
                },
            }],
        }
        const result = parsePostmanCollection(col)
        expect(result.requests[0].data.headers).toEqual({ 'X-Active': 'yes' })
    })

    it('omits headers when none are present', () => {
        const col = {
            info: { name: 'No Headers', schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json' },
            item: [{ name: 'R', request: { method: 'GET', header: [], url: { raw: '/x' } } }],
        }
        const result = parsePostmanCollection(col)
        expect(result.requests[0].data.headers).toBeUndefined()
    })

    it('handles an empty item array', () => {
        const col = {
            info: { name: 'Empty', schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json' },
            item: [],
        }
        const result = parsePostmanCollection(col)
        expect(result.requests).toHaveLength(0)
        expect(result.folders).toHaveLength(0)
    })

    it('throws when passed null', () => {
        expect(() => parsePostmanCollection(null)).toThrow('Invalid Postman collection')
    })

    it('throws when info.name is missing', () => {
        expect(() => parsePostmanCollection({ info: {}, item: [] })).toThrow('Invalid Postman collection')
    })

    it('throws when schema version is not v2', () => {
        expect(() => parsePostmanCollection({
            info: { name: 'Old', schema: 'https://schema.getpostman.com/json/collection/v1.0.0/collection.json' },
            item: [],
        })).toThrow('Unsupported Postman schema')
    })
})

// ─── exportToPostman ──────────────────────────────────────────────────────────

describe('exportToPostman', () => {
    const mockCollection: Collection = {
        id:          '1',
        name:        'My Export',
        description: null,
        created_at:  0,
        updated_at:  0,
        folders: [
            {
                id:            'f1',
                collection_id: '1',
                name:          'Auth',
                description:   null,
                sort_order:    0,
                created_at:    0,
                updated_at:    0,
                requests: [
                    {
                        id:            'r1',
                        collection_id: '1',
                        folder_id:     'f1',
                        name:          'Login',
                        sort_order:    0,
                        created_at:    0,
                        updated_at:    0,
                        data: {
                            method:  'POST',
                            url:     '/api/login',
                            headers: { 'Content-Type': 'application/json' },
                            body:    '{"email":"test@example.com"}',
                        },
                    },
                ],
            },
        ],
        requests: [
            {
                id:            'r2',
                collection_id: '1',
                folder_id:     null,
                name:          'Health Check',
                sort_order:    0,
                created_at:    0,
                updated_at:    0,
                data: { method: 'GET', url: '/health' },
            },
        ],
    }

    it('sets the correct Postman schema in info', () => {
        const out = exportToPostman(mockCollection)
        expect(out.info.name).toBe('My Export')
        expect(out.info.schema).toContain('v2.1.0')
    })

    it('exports folders as nested items', () => {
        const out = exportToPostman(mockCollection)
        const folder = out.item.find(i => Array.isArray(i.item))
        expect(folder?.name).toBe('Auth')
        expect(folder?.item).toHaveLength(1)
        expect(folder?.item?.[0].name).toBe('Login')
    })

    it('exports top-level requests', () => {
        const out = exportToPostman(mockCollection)
        const req = out.item.find(i => !Array.isArray(i.item))
        expect(req?.name).toBe('Health Check')
        expect(req?.request?.method).toBe('GET')
    })

    it('serializes request headers correctly', () => {
        const out    = exportToPostman(mockCollection)
        const folder = out.item.find(i => Array.isArray(i.item))!
        const login  = folder.item![0]
        expect(login.request?.header).toContainEqual({ key: 'Content-Type', value: 'application/json' })
    })

    it('serializes request body as raw mode', () => {
        const out    = exportToPostman(mockCollection)
        const folder = out.item.find(i => Array.isArray(i.item))!
        const login  = folder.item![0]
        expect(login.request?.body).toEqual({ mode: 'raw', raw: '{"email":"test@example.com"}' })
    })

    it('omits body when request has no body', () => {
        const out = exportToPostman(mockCollection)
        const req = out.item.find(i => !Array.isArray(i.item))!
        expect(req.request?.body).toBeUndefined()
    })

    it('exports an empty collection with no items', () => {
        const empty: Collection = {
            id: '2', name: 'Empty', description: null, created_at: 0, updated_at: 0,
            folders: [], requests: [],
        }
        const out = exportToPostman(empty)
        expect(out.item).toHaveLength(0)
    })
})
