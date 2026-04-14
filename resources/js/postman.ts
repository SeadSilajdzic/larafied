/**
 * Postman Collection v2.1 import / export utilities.
 *
 * Import: parse a Postman v2.1 JSON blob → intermediate structure suitable
 *         for creating via the Larafied API.
 * Export: serialize a Larafied Collection → Postman v2.1 JSON.
 */

import type { Collection, RequestData } from './types'

// ─── Internal Postman shape (minimal, covers what we care about) ──────────────

interface PostmanHeader { key: string; value: string; disabled?: boolean }

interface PostmanBody {
    mode: string
    raw?:     string
    urlencoded?: Array<{ key: string; value: string; disabled?: boolean }>
    formdata?:   Array<{ key: string; value: string; disabled?: boolean }>
}

interface PostmanUrl {
    raw?: string
}

interface PostmanRequest {
    method: string
    header?: PostmanHeader[]
    url?:    PostmanUrl | string
    body?:   PostmanBody
}

interface PostmanItem {
    name:    string
    request?: PostmanRequest
    item?:   PostmanItem[]  // folder has this instead of request
}

interface PostmanCollection {
    info: {
        name:   string
        schema: string
    }
    item: PostmanItem[]
}

// ─── Public import types ──────────────────────────────────────────────────────

export interface ImportedFolder {
    name:     string
    requests: ImportedRequest[]
}

export interface ImportedRequest {
    name:    string
    data:    RequestData
}

export interface ImportedCollection {
    name:     string
    folders:  ImportedFolder[]
    requests: ImportedRequest[]
}

// ─── Import ───────────────────────────────────────────────────────────────────

function parseUrl(url: PostmanUrl | string | undefined): string {
    if (!url) return ''
    if (typeof url === 'string') return url
    return url.raw ?? ''
}

function parseHeaders(headers: PostmanHeader[] | undefined): Record<string, string> {
    const result: Record<string, string> = {}
    for (const h of headers ?? []) {
        if (!h.disabled && h.key) result[h.key] = h.value ?? ''
    }
    return result
}

function parseBody(body: PostmanBody | undefined): string | undefined {
    if (!body) return undefined
    if (body.mode === 'raw' && body.raw) return body.raw
    if (body.mode === 'urlencoded' && body.urlencoded) {
        return body.urlencoded
            .filter(p => !p.disabled)
            .map(p => `${encodeURIComponent(p.key)}=${encodeURIComponent(p.value)}`)
            .join('&')
    }
    return undefined
}

function parseItem(item: PostmanItem): ImportedRequest {
    const req  = item.request ?? { method: 'GET' }
    const hdrs = parseHeaders(req.header)
    return {
        name: item.name,
        data: {
            method:  (req.method ?? 'GET').toUpperCase(),
            url:     parseUrl(req.url),
            headers: Object.keys(hdrs).length > 0 ? hdrs : undefined,
            body:    parseBody(req.body),
        },
    }
}

/**
 * Parse a Postman Collection v2.1 JSON blob.
 * Throws if the payload is not recognisable as a Postman collection.
 */
export function parsePostmanCollection(json: unknown): ImportedCollection {
    if (typeof json !== 'object' || json === null) {
        throw new Error('Invalid Postman collection: expected a JSON object')
    }

    const col = json as Record<string, unknown>

    // Light schema check
    const info = col['info'] as Record<string, unknown> | undefined
    if (!info?.['name'] || typeof info['name'] !== 'string') {
        throw new Error('Invalid Postman collection: missing info.name')
    }

    const schema = (info['schema'] as string | undefined) ?? ''
    if (!schema.includes('v2')) {
        throw new Error(`Unsupported Postman schema: ${schema || '(none)'}. Only v2.1 is supported.`)
    }

    const items = (col['item'] as PostmanItem[] | undefined) ?? []
    const folders:  ImportedFolder[]  = []
    const requests: ImportedRequest[] = []

    for (const item of items) {
        if (Array.isArray(item.item)) {
            // It's a folder
            folders.push({
                name:     item.name,
                requests: item.item.filter(i => !Array.isArray(i.item)).map(parseItem),
            })
        } else {
            // Top-level request
            requests.push(parseItem(item))
        }
    }

    return { name: info['name'] as string, folders, requests }
}

// ─── Export ───────────────────────────────────────────────────────────────────

function serializeRequest(req: ImportedRequest | { name: string; data: RequestData }): PostmanItem {
    const data    = req.data
    const headers = Object.entries(data.headers ?? {}).map(([key, value]) => ({ key, value }))

    const urlItem: PostmanUrl = { raw: data.url }

    const body: PostmanBody | undefined = data.body
        ? { mode: 'raw', raw: data.body }
        : undefined

    return {
        name: req.name,
        request: {
            method: data.method,
            header: headers,
            url:    urlItem,
            ...(body ? { body } : {}),
        },
    }
}

/**
 * Serialize a Larafied Collection to Postman Collection v2.1 format.
 */
export function exportToPostman(collection: Collection): PostmanCollection {
    const items: PostmanItem[] = []

    // Folders first
    for (const folder of collection.folders) {
        items.push({
            name: folder.name,
            item: folder.requests.map(r => serializeRequest({ name: r.name, data: r.data })),
        })
    }

    // Top-level requests
    for (const req of collection.requests) {
        items.push(serializeRequest({ name: req.name, data: req.data }))
    }

    return {
        info: {
            name:   collection.name,
            schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        },
        item: items,
    }
}

/**
 * Trigger a browser download of a JSON blob.
 */
export function downloadJson(data: unknown, filename: string): void {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
}
