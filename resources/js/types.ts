export interface LarafiedConfig {
    prefix: string
    title: string
    version: string
}

export interface RouteGroup {
    group: string
    routes: Route[]
}

export interface Route {
    methods: string[]
    uri: string
    name: string | null
    middleware: string[]
    group: string
    parameters: string[]
    action: string
}

export interface Collection {
    id: string
    name: string
    description: string | null
    requests: SavedRequest[]
    created_at: number
    updated_at: number
}

export interface SavedRequest {
    id: string
    collection_id: string
    name: string
    data: RequestData
    sort_order: number
    created_at: number
    updated_at: number
}

export interface RequestData {
    method: string
    url: string
    headers?: Record<string, string>
    body?: string | null
    query?: Record<string, string>
}

export interface HeaderRow {
    key: string
    value: string
    enabled: boolean
}

export interface Environment {
    id: string
    name: string
    variables: EnvVariable[]
    is_active: boolean
    created_at: number
    updated_at: number
}

export interface EnvVariable {
    key: string
    value: string
    secret?: boolean
}

export interface ProxyResponse {
    status: number
    headers: Record<string, string>
    body: string
    duration_ms: number
    content_type: string
    size: number
}

export interface ActiveRequest {
    method: string
    url: string
    headers: HeaderRow[]
    body: string
}
