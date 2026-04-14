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

export interface Folder {
    id: string
    collection_id: string
    name: string
    description: string | null
    sort_order: number
    requests: SavedRequest[]
    created_at: number
    updated_at: number
}

export interface Collection {
    id: string
    name: string
    description: string | null
    requests: SavedRequest[]
    folders: Folder[]
    created_at: number
    updated_at: number
}

export interface SavedRequest {
    id: string
    collection_id: string
    folder_id: string | null
    name: string
    data: RequestData
    sort_order: number
    created_at: number
    updated_at: number
}

export interface RouteNote {
    id: string
    method: string
    uri: string
    note: string
    updated_at: number
}

export type AssertionType = 'status_equals' | 'body_contains' | 'json_path_equals' | 'header_equals'

export interface Assertion {
    type:  AssertionType
    value: string
    key?:  string   // path for json_path_equals; header name for header_equals
}

export interface AssertionResult {
    assertion: Assertion
    passed:    boolean
    message:   string
}

export interface RequestData {
    method:            string
    url:               string
    headers?:          Record<string, string>
    body?:             string | null
    query?:            Record<string, string>
    auth?:             AuthConfig
    preRequestScript?: string
    assertions?:       Assertion[]
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

export interface QueryLogEntry {
    sql: string
    bindings: unknown[]
    time_ms: number
}

export interface SqlResult {
    rows: Record<string, unknown>[]
    count: number
    duration_ms: number
    connection: string
}

export interface ProxyResponse {
    status: number
    headers: Record<string, string>
    body: string
    duration_ms: number
    content_type: string
    size: number
    queries: QueryLogEntry[]
}

export type AuthType = 'none' | 'bearer' | 'basic' | 'apikey'

export interface AuthConfig {
    type:      AuthType
    token?:    string
    username?: string
    password?: string
    key?:      string
    value?:    string
    in?:       'header' | 'query'
}

export interface ActiveRequest {
    method:           string
    url:              string
    headers:          HeaderRow[]
    body:             string
    auth:             AuthConfig
    preRequestScript: string
    assertions:       Assertion[]
}

export function defaultAuth(): AuthConfig {
    return { type: 'none' }
}
