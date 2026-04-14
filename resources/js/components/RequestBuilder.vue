<script setup lang="ts">
import { nextTick, ref, computed } from 'vue'
import { buildCurlCommand, copyToClipboard } from '../utilities/copyCurl'
import Prism from 'prismjs'
import 'prismjs/components/prism-json'
import 'prismjs/components/prism-graphql'
import 'prismjs/components/prism-sql'
import type { ActiveRequest, AuthType } from '../types'

type BodyType = 'json' | 'raw' | 'graphql' | 'sql'

const props = defineProps<{
    request:  ActiveRequest
    sending:  boolean
    queryLog: boolean
    bodyType: BodyType
    features: string[]
    envVars?: Record<string, string>
}>()

const emit = defineEmits<{
    send:               []
    sendSql:            []
    sendGraphql:        [body: string]
    'update:bodyType':  [val: BodyType]
    'update:queryLog':  [val: boolean]
    upgrade:            []
}>()

function hasFeature(feature: string): boolean {
    return props.features.includes(feature)
}

const activeTab = ref<'headers' | 'body' | 'auth' | 'script' | 'tests'>('headers')

const ASSERTION_TYPES: { value: string; label: string }[] = [
    { value: 'status_equals',    label: 'Status equals'    },
    { value: 'body_contains',    label: 'Body contains'    },
    { value: 'json_path_equals', label: 'JSON path equals' },
    { value: 'header_equals',    label: 'Header equals'    },
]

function addAssertion(): void {
    props.request.assertions.push({ type: 'status_equals', value: '' })
}

function removeAssertion(index: number): void {
    props.request.assertions.splice(index, 1)
}

const AUTH_TYPES: { value: AuthType; label: string }[] = [
    { value: 'none',   label: 'None'    },
    { value: 'bearer', label: 'Bearer'  },
    { value: 'basic',  label: 'Basic'   },
    { value: 'apikey', label: 'API Key' },
]

// Per-mode body content buffers — preserved when switching modes
const bodyContents = ref<Record<BodyType, string>>({
    json:    props.request.body,
    raw:     '',
    graphql: '',
    sql:     '',
})

// GraphQL-specific state
const gqlVariables = ref('')
const showGqlVars  = ref(false)

// Template refs for scroll sync
const bodyPre      = ref<HTMLPreElement | null>(null)
const gqlVarsPre   = ref<HTMLPreElement | null>(null)

const FEATURE_FOR_BODY_TYPE: Partial<Record<BodyType, string>> = {
    graphql: 'graphql',
    sql:     'sql_console',
}

function switchBodyType(newType: BodyType): void {
    if (newType === props.bodyType) return
    const required = FEATURE_FOR_BODY_TYPE[newType]
    if (required && !hasFeature(required)) {
        emit('upgrade')
        return
    }
    // Save current content into its buffer
    bodyContents.value[props.bodyType] = props.request.body
    // Restore the new mode's buffer
    props.request.body = bodyContents.value[newType]
    // GraphQL is always POST
    if (newType === 'graphql' && props.request.method === 'GET') {
        props.request.method = 'POST'
    }
    emit('update:bodyType', newType)
}

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']

const JSON_PLACEHOLDER = '{\n  "key": "value"\n}'
const GQL_PLACEHOLDER  = 'query {\n  \n}'
const SQL_PLACEHOLDER  = 'SELECT * FROM users LIMIT 10'

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

const BODY_TYPES: { value: BodyType; label: string }[] = [
    { value: 'json',    label: 'JSON'    },
    { value: 'raw',     label: 'Raw'     },
    { value: 'graphql', label: 'GraphQL' },
    { value: 'sql',     label: 'SQL'     },
]

// --- URL variable preview ---
type UrlToken = { type: 'text' | 'resolved' | 'unresolved'; displayText: string }

const urlPreviewTokens = computed((): UrlToken[] | null => {
    const url = props.request.url
    if (!url || !url.includes('{{')) return null

    const tokens: UrlToken[] = []
    const vars = props.envVars ?? {}

    // Prepend origin for relative paths so the preview shows the full URL
    const isRelative = !url.startsWith('http://') && !url.startsWith('https://')
    if (isRelative) tokens.push({ type: 'text', displayText: window.location.origin })

    const regex = /\{\{(\w+)\}\}/g
    let last = 0
    let match: RegExpExecArray | null
    while ((match = regex.exec(url)) !== null) {
        if (match.index > last) tokens.push({ type: 'text', displayText: url.slice(last, match.index) })
        const key   = match[1]
        const value = vars[key]
        tokens.push({
            type:        value !== undefined ? 'resolved' : 'unresolved',
            displayText: value !== undefined ? value : `{{${key}}}`,
        })
        last = match.index + match[0].length
    }
    if (last < url.length) tokens.push({ type: 'text', displayText: url.slice(last) })

    return tokens
})

function resolvePreview(text: string): string {
    const vars = props.envVars ?? {}
    return text.replace(/\{\{(\w+)\}\}/g, (_, key) => vars[key] ?? `{{${key}}}`)
}

// --- URL split bar ---
const baseUrl = window.location.origin

const isExternalUrl = computed(() =>
    props.request.url.startsWith('http://') || props.request.url.startsWith('https://'),
)

function escapeHtml(str: string): string {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

const highlightedBody = computed(() => {
    if (!props.request.body) return ''
    if (props.bodyType === 'json') {
        try { return Prism.highlight(props.request.body, Prism.languages.json, 'json') } catch { /* fall through */ }
    }
    if (props.bodyType === 'graphql') {
        try { return Prism.highlight(props.request.body, Prism.languages.graphql, 'graphql') } catch { /* fall through */ }
    }
    if (props.bodyType === 'sql') {
        try { return Prism.highlight(props.request.body, Prism.languages.sql, 'sql') } catch { /* fall through */ }
    }
    return escapeHtml(props.request.body)
})

const highlightedGqlVars = computed(() => {
    if (!gqlVariables.value) return ''
    try { return Prism.highlight(gqlVariables.value, Prism.languages.json, 'json') } catch { /* fall through */ }
    return escapeHtml(gqlVariables.value)
})

// Mirror textarea scroll position to the <pre> overlay
function syncBodyScroll(e: Event): void {
    if (bodyPre.value) {
        const ta = e.target as HTMLTextAreaElement
        bodyPre.value.scrollTop  = ta.scrollTop
        bodyPre.value.scrollLeft = ta.scrollLeft
    }
}

function syncGqlVarsScroll(e: Event): void {
    if (gqlVarsPre.value) {
        const ta = e.target as HTMLTextAreaElement
        gqlVarsPre.value.scrollTop  = ta.scrollTop
        gqlVarsPre.value.scrollLeft = ta.scrollLeft
    }
}

function addHeader(): void {
    props.request.headers.push({ key: '', value: '', enabled: true })
}

function removeHeader(index: number): void {
    props.request.headers.splice(index, 1)
}

function handleSend(): void {
    if (props.bodyType === 'sql') {
        if (!props.request.body.trim()) return
        emit('sendSql')
        return
    }

    if (!props.request.url.trim()) return

    if (props.bodyType === 'graphql') {
        let variables: Record<string, unknown> | undefined
        if (showGqlVars.value && gqlVariables.value.trim()) {
            try { variables = JSON.parse(gqlVariables.value) } catch { /* invalid JSON — send without */ }
        }
        const payload = variables !== undefined
            ? { query: props.request.body, variables }
            : { query: props.request.body }
        emit('sendGraphql', JSON.stringify(payload))
        return
    }

    emit('send')
}

function handleKeydown(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        handleSend()
    }
}

// --- Copy as cURL ---
const curlCopied = ref(false)

async function handleCopyCurl(): Promise<void> {
    const headers: Record<string, string> = {}
    for (const h of props.request.headers) {
        if (h.enabled && h.key.trim()) headers[h.key.trim()] = h.value
    }

    const url  = props.request.url.startsWith('http')
        ? props.request.url
        : `${window.location.origin}${props.request.url.startsWith('/') ? '' : '/'}${props.request.url}`
    const curl = buildCurlCommand(props.request.method, url, headers, props.request.body || undefined)

    await copyToClipboard(curl)
    curlCopied.value = true
    setTimeout(() => { curlCopied.value = false }, 2000)
}

// --- Resize drag handle ---
const textareaHeight = ref(150)

function startResize(e: MouseEvent): void {
    const startY = e.clientY
    const startH = textareaHeight.value

    function onMove(ev: MouseEvent): void {
        textareaHeight.value = Math.max(80, Math.min(600, startH + ev.clientY - startY))
    }

    function onUp(): void {
        document.removeEventListener('mousemove', onMove)
        document.removeEventListener('mouseup', onUp)
    }

    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onUp)
}

// --- Body key handling (JSON auto-pairs) ---
const AUTO_PAIRS: Record<string, string> = { '"': '"', '{': '}', '[': ']', '(': ')' }

async function applyTextareaEdit(
    textarea: HTMLTextAreaElement,
    newValue: string,
    newStart: number,
    newEnd: number,
): Promise<void> {
    textarea.value     = newValue
    props.request.body = newValue
    await nextTick()
    textarea.selectionStart = newStart
    textarea.selectionEnd   = newEnd
}

async function handleBodyKeydown(e: KeyboardEvent): Promise<void> {
    const textarea = e.target as HTMLTextAreaElement

    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        handleSend()
        return
    }

    if (e.key === 'Tab') {
        e.preventDefault()
        const { selectionStart: s, selectionEnd: en, value } = textarea
        await applyTextareaEdit(textarea, value.substring(0, s) + '    ' + value.substring(en), s + 4, s + 4)
        return
    }

    if (props.bodyType !== 'json') return

    const { selectionStart: start, selectionEnd: end, value } = textarea

    if (e.key === ':') {
        const before = value.substring(0, start)
        const match  = before.match(/([a-zA-Z_$][a-zA-Z0-9_$]*)$/)
        if (match && before[before.length - match[1].length - 1] !== '"') {
            e.preventDefault()
            const word      = match[1]
            const wordStart = start - word.length
            const newValue  = value.substring(0, wordStart) + '"' + word + '"' + ':' + value.substring(end)
            const newPos    = wordStart + word.length + 3
            await applyTextareaEdit(textarea, newValue, newPos, newPos)
            return
        }
    }

    if (e.key === 'Backspace' && start === end && start > 0) {
        const before = value[start - 1]
        const after  = value[start]
        if (before in AUTO_PAIRS && AUTO_PAIRS[before] === after) {
            e.preventDefault()
            const newValue = value.substring(0, start - 1) + value.substring(start + 1)
            await applyTextareaEdit(textarea, newValue, start - 1, start - 1)
            return
        }
    }

    if (e.key in AUTO_PAIRS) {
        e.preventDefault()
        const open  = e.key
        const close = AUTO_PAIRS[open]

        if (start !== end) {
            const sel      = value.substring(start, end)
            const newValue = value.substring(0, start) + open + sel + close + value.substring(end)
            await applyTextareaEdit(textarea, newValue, start + 1, end + 1)
        } else if (open === close && value[start] === close) {
            await applyTextareaEdit(textarea, value, start + 1, start + 1)
        } else {
            const newValue = value.substring(0, start) + open + close + value.substring(end)
            await applyTextareaEdit(textarea, newValue, start + 1, start + 1)
        }
    }
}
</script>

<template>
    <div class="flex flex-col border-b border-gray-800 shrink-0">

        <!-- SQL Console header -->
        <div
            v-if="bodyType === 'sql'"
            class="flex items-center gap-2 px-3 py-2 border-b border-gray-800"
        >
            <span class="text-xs font-mono font-semibold text-indigo-400 shrink-0">SQL Console</span>
            <span class="flex-1 text-xs text-gray-700 italic truncate">runs against the app database — SELECT only</span>
            <button
                class="px-3 py-1.5 rounded text-xs font-semibold transition-colors shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="sending
                    ? 'bg-indigo-700 text-indigo-200 cursor-wait'
                    : 'bg-indigo-600 hover:bg-indigo-500 text-white'"
                :disabled="sending || !request.body.trim()"
                @click="handleSend"
            >
                {{ sending ? 'Running…' : 'Run' }}
            </button>
        </div>

        <!-- Normal URL bar -->
        <div
            v-else
            class="flex items-center gap-2 px-3 py-2 border-b border-gray-800"
        >
            <select
                v-model="request.method"
                class="bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs font-mono font-semibold focus:outline-none focus:border-indigo-500 shrink-0 cursor-pointer"
                :class="METHOD_COLORS[request.method] ?? 'text-gray-400'"
            >
                <option v-for="m in METHODS" :key="m" :value="m">{{ m }}</option>
            </select>

            <div class="flex flex-1 items-stretch bg-gray-900 border border-gray-700 rounded overflow-hidden focus-within:border-indigo-500 min-w-0 transition-colors">
                <span
                    v-if="!isExternalUrl"
                    class="flex items-center px-2 text-xs font-mono text-gray-600 border-r border-gray-800 bg-gray-900/80 select-none whitespace-nowrap shrink-0"
                    title="Base URL (read-only)"
                >
                    {{ baseUrl }}
                </span>
                <input
                    v-model="request.url"
                    type="text"
                    :placeholder="bodyType === 'graphql'
                        ? (isExternalUrl ? 'https://example.com/graphql' : '/graphql')
                        : (isExternalUrl ? 'https://example.com/api/endpoint' : '/api/endpoint')"
                    class="flex-1 bg-transparent px-2 py-1.5 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none min-w-0"
                    @keydown="handleKeydown"
                />
            </div>

            <!-- Query log toggle -->
            <button
                class="p-1.5 rounded transition-colors shrink-0 relative"
                :class="hasFeature('query_log')
                    ? (queryLog
                        ? 'text-indigo-400 bg-indigo-500/20 hover:bg-indigo-500/30'
                        : 'text-gray-600 hover:text-gray-400 hover:bg-gray-800')
                    : 'text-gray-700 hover:text-gray-600'"
                :title="hasFeature('query_log') ? 'Toggle query log' : 'Query log requires Pro — upgrade to unlock'"
                @click="hasFeature('query_log') ? emit('update:queryLog', !queryLog) : emit('upgrade')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <ellipse cx="12" cy="5" rx="9" ry="3" stroke-width="1.8"/>
                    <path stroke-width="1.8" d="M3 5v6c0 1.657 4.03 3 9 3s9-1.343 9-3V5"/>
                    <path stroke-width="1.8" d="M3 11v6c0 1.657 4.03 3 9 3s9-1.343 9-3v-6"/>
                </svg>
                <svg
                    v-if="!hasFeature('query_log')"
                    class="absolute -top-0.5 -right-0.5 w-2 h-2 text-amber-500"
                    fill="currentColor" viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
            </button>

            <!-- Copy as cURL -->
            <button
                v-if="request.url.trim()"
                class="p-1.5 rounded transition-colors shrink-0"
                :class="curlCopied
                    ? 'text-emerald-400 bg-emerald-500/20'
                    : 'text-gray-600 hover:text-gray-300 hover:bg-gray-800'"
                :title="curlCopied ? 'Copied!' : 'Copy as cURL'"
                @click="handleCopyCurl"
            >
                <svg v-if="curlCopied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </button>

            <button
                class="px-3 py-1.5 rounded text-xs font-semibold transition-colors shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="sending
                    ? 'bg-indigo-700 text-indigo-200 cursor-wait'
                    : 'bg-indigo-600 hover:bg-indigo-500 text-white'"
                :disabled="sending || !request.url.trim()"
                @click="handleSend"
            >
                {{ sending ? 'Sending…' : 'Send' }}
            </button>
        </div>

        <!-- Variable resolution preview (only when URL contains {{vars}}) -->
        <div
            v-if="urlPreviewTokens"
            class="flex flex-wrap items-center gap-x-0 gap-y-0.5 px-3 py-1.5 border-b border-gray-800/60 bg-gray-900/40 font-mono text-xs leading-tight"
        >
            <template v-for="(t, i) in urlPreviewTokens" :key="i">
                <span v-if="t.type === 'text'" class="text-gray-600 break-all">{{ t.displayText }}</span>
                <span
                    v-else-if="t.type === 'resolved'"
                    class="inline-block bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded px-1 mx-px"
                >{{ t.displayText }}</span>
                <span
                    v-else
                    class="inline-block bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded px-1 mx-px"
                    title="Variable not found in active environment"
                >{{ t.displayText }}</span>
            </template>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-800 px-3">
            <!-- Headers tab -->
            <button
                class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px"
                :class="activeTab === 'headers'
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                @click="activeTab = 'headers'"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span>Headers</span>
                <span
                    v-if="request.headers.filter(h => h.enabled && h.key).length"
                    class="ml-0.5 text-indigo-400"
                >
                    ({{ request.headers.filter(h => h.enabled && h.key).length }})
                </span>
            </button>

            <!-- Auth tab -->
            <button
                class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px relative"
                :class="activeTab === 'auth'
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                :title="!hasFeature('auth_helpers') ? 'Auth helpers require Pro — upgrade to unlock' : undefined"
                @click="hasFeature('auth_helpers') ? (activeTab = 'auth') : emit('upgrade')"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span>Auth</span>
                <span
                    v-if="request.auth.type !== 'none'"
                    class="ml-0.5 text-indigo-400"
                >({{ request.auth.type }})</span>
                <svg
                    v-if="!hasFeature('auth_helpers')"
                    class="w-2 h-2 shrink-0 text-amber-500"
                    fill="currentColor" viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
            </button>

            <!-- Script tab -->
            <button
                class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px relative"
                :class="activeTab === 'script'
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                :title="!hasFeature('pre_request_scripts') ? 'Pre-request scripts require Pro — upgrade to unlock' : undefined"
                @click="hasFeature('pre_request_scripts') ? (activeTab = 'script') : emit('upgrade')"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                <span>Script</span>
                <span
                    v-if="request.preRequestScript.trim()"
                    class="ml-0.5 w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block"
                />
                <svg
                    v-if="!hasFeature('pre_request_scripts')"
                    class="w-2 h-2 shrink-0 text-amber-500"
                    fill="currentColor" viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
            </button>

            <!-- Body tab -->
            <button
                class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px"
                :class="activeTab === 'body'
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                @click="activeTab = 'body'"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                <span>Body</span>
            </button>

            <!-- Tests tab -->
            <button
                class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px"
                :class="activeTab === 'tests'
                    ? 'border-emerald-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                @click="activeTab = 'tests'"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Tests</span>
                <span
                    v-if="request.assertions.length"
                    class="ml-0.5 text-emerald-400"
                >({{ request.assertions.length }})</span>
            </button>
        </div>

        <!-- Headers tab -->
        <div v-if="activeTab === 'headers'" class="px-3 py-2 max-h-40 overflow-y-auto">
            <div
                v-for="(header, i) in request.headers"
                :key="i"
                class="mb-1.5"
            >
                <div class="flex items-center gap-2">
                    <input v-model="header.enabled" type="checkbox" class="accent-indigo-500 shrink-0" />
                    <input
                        v-model="header.key"
                        type="text"
                        placeholder="Header name"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                    />
                    <input
                        v-model="header.value"
                        type="text"
                        placeholder="Value"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                    />
                    <button class="text-gray-600 hover:text-red-400 transition-colors shrink-0 text-sm leading-none" @click="removeHeader(i)">×</button>
                </div>
                <!-- Resolved value preview when header value contains {{vars}} -->
                <div
                    v-if="header.value.includes('{{') && header.enabled"
                    class="ml-5 mt-0.5 text-xs font-mono text-emerald-600/70 truncate"
                >→ {{ resolvePreview(header.value) }}</div>
            </div>
            <button class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors mt-1" @click="addHeader">
                + Add header
            </button>
        </div>

        <!-- Script tab -->
        <div v-if="activeTab === 'script'" class="flex flex-col px-3 py-2">
            <p class="text-xs text-gray-600 mb-2">
                Runs before the request is sent. Use <code class="text-indigo-400">pm.request</code> to modify URL/headers/body,
                and <code class="text-indigo-400">pm.environment.get/set</code> to read/write variables.
            </p>
            <div
                class="relative rounded border overflow-hidden transition-colors focus-within:border-indigo-500 border-gray-700"
                style="height: 150px"
            >
                <textarea
                    v-model="request.preRequestScript"
                    placeholder="// Example:&#10;pm.request.headers['Authorization'] = 'Bearer ' + pm.environment.get('TOKEN')"
                    class="editor-textarea absolute inset-0 w-full h-full"
                    style="color: #d1d5db !important; -webkit-text-fill-color: #d1d5db !important; background: rgb(17 24 39) !important;"
                    spellcheck="false"
                    autocomplete="off"
                    @keydown="handleKeydown"
                />
            </div>
            <button
                class="mt-1.5 self-start text-xs text-gray-600 hover:text-gray-400 transition-colors"
                @click="request.preRequestScript = ''"
            >Clear</button>
        </div>

        <!-- Auth tab -->
        <div v-if="activeTab === 'auth'" class="px-3 py-3 flex flex-col gap-3">

            <!-- Auth type selector -->
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 shrink-0 w-16">Type</span>
                <div class="flex bg-gray-950 rounded border border-gray-800 p-0.5 gap-0.5">
                    <button
                        v-for="at in AUTH_TYPES"
                        :key="at.value"
                        class="px-2.5 py-1 text-xs font-medium rounded transition-all"
                        :class="request.auth.type === at.value
                            ? 'bg-gray-700 text-white shadow-sm'
                            : 'text-gray-500 hover:text-gray-300'"
                        @click="request.auth.type = at.value"
                    >
                        {{ at.label }}
                    </button>
                </div>
            </div>

            <!-- Bearer token -->
            <div v-if="request.auth.type === 'bearer'" class="flex items-center gap-2">
                <span class="text-xs text-gray-500 shrink-0 w-16">Token</span>
                <input
                    v-model="request.auth.token"
                    type="text"
                    placeholder="eyJhbGciOiJIUzI1NiIs…"
                    class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                />
            </div>

            <!-- Basic auth -->
            <template v-if="request.auth.type === 'basic'">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 shrink-0 w-16">Username</span>
                    <input
                        v-model="request.auth.username"
                        type="text"
                        placeholder="username"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 shrink-0 w-16">Password</span>
                    <input
                        v-model="request.auth.password"
                        type="password"
                        placeholder="••••••••"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                    />
                </div>
            </template>

            <!-- API Key -->
            <template v-if="request.auth.type === 'apikey'">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 shrink-0 w-16">Key</span>
                    <input
                        v-model="request.auth.key"
                        type="text"
                        placeholder="X-Api-Key"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 shrink-0 w-16">Value</span>
                    <input
                        v-model="request.auth.value"
                        type="text"
                        placeholder="your-api-key"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 shrink-0 w-16">Add to</span>
                    <div class="flex bg-gray-950 rounded border border-gray-800 p-0.5 gap-0.5">
                        <button
                            v-for="loc in (['header', 'query'] as const)"
                            :key="loc"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-all capitalize"
                            :class="(request.auth.in ?? 'header') === loc
                                ? 'bg-gray-700 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-300'"
                            @click="request.auth.in = loc"
                        >
                            {{ loc }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Tests tab -->
        <div v-if="activeTab === 'tests'" class="px-3 py-3 flex flex-col gap-2 max-h-52 overflow-y-auto">
            <p class="text-xs text-gray-600 mb-1">
                Assertions run automatically when the request is sent, and via
                <code class="text-indigo-400">php artisan larafied:test</code> in CI.
            </p>

            <div
                v-for="(assertion, i) in request.assertions"
                :key="i"
                class="flex items-center gap-2"
            >
                <!-- Type selector -->
                <select
                    v-model="assertion.type"
                    class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-300 focus:outline-none focus:border-indigo-500 shrink-0"
                >
                    <option v-for="at in ASSERTION_TYPES" :key="at.value" :value="at.value">{{ at.label }}</option>
                </select>

                <!-- Key (for json_path and header) -->
                <input
                    v-if="assertion.type === 'json_path_equals' || assertion.type === 'header_equals'"
                    v-model="assertion.key"
                    type="text"
                    :placeholder="assertion.type === 'json_path_equals' ? 'data.id' : 'Content-Type'"
                    class="w-28 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                />

                <!-- Value -->
                <input
                    v-model="assertion.value"
                    type="text"
                    :placeholder="assertion.type === 'status_equals' ? '200' : 'expected value'"
                    class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                />

                <button
                    class="text-gray-600 hover:text-red-400 transition-colors shrink-0 text-sm leading-none"
                    @click="removeAssertion(i)"
                >×</button>
            </div>

            <button
                class="text-xs text-emerald-500 hover:text-emerald-400 transition-colors mt-1 self-start"
                @click="addAssertion"
            >
                + Add assertion
            </button>
        </div>

        <!-- Body tab -->
        <div v-if="activeTab === 'body'" class="flex flex-col px-3 py-2">

            <!-- Body type selector — segmented control -->
            <div class="flex items-center mb-2 self-start bg-gray-950 rounded border border-gray-800 p-0.5 gap-0.5">
                <button
                    v-for="bt in BODY_TYPES"
                    :key="bt.value"
                    class="px-2.5 py-1 text-xs font-medium rounded transition-all flex items-center gap-1"
                    :class="bodyType === bt.value
                        ? 'bg-gray-700 text-white shadow-sm'
                        : 'text-gray-500 hover:text-gray-300'"
                    :title="FEATURE_FOR_BODY_TYPE[bt.value] && !hasFeature(FEATURE_FOR_BODY_TYPE[bt.value]!) ? 'Pro feature — upgrade to unlock' : undefined"
                    @click="switchBodyType(bt.value)"
                >
                    {{ bt.label }}
                    <svg
                        v-if="FEATURE_FOR_BODY_TYPE[bt.value] && !hasFeature(FEATURE_FOR_BODY_TYPE[bt.value]!)"
                        class="w-2.5 h-2.5 shrink-0 text-amber-500"
                        fill="currentColor" viewBox="0 0 20 20"
                    >
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <!-- Main body editor -->
            <div
                class="relative rounded-t border overflow-hidden transition-colors focus-within:border-indigo-500 border-gray-700"
                :style="{ height: textareaHeight + 'px' }"
            >
                <!-- Syntax-highlighted layer (always visible when there's content) -->
                <pre
                    ref="bodyPre"
                    v-show="request.body"
                    class="editor-pre absolute inset-0 m-0 overflow-auto pointer-events-none select-none"
                    v-html="highlightedBody"
                />
                <!-- Transparent textarea — caret and selection visible, text transparent -->
                <textarea
                    v-model="request.body"
                    :placeholder="bodyType === 'json' ? JSON_PLACEHOLDER : bodyType === 'graphql' ? GQL_PLACEHOLDER : bodyType === 'sql' ? SQL_PLACEHOLDER : 'Request body…'"
                    class="editor-textarea absolute inset-0 w-full h-full"
                    spellcheck="false"
                    autocomplete="off"
                    @keydown="handleBodyKeydown"
                    @scroll="syncBodyScroll"
                />
            </div>

            <!-- Resize handle -->
            <div
                class="w-full h-2 bg-gray-800 border border-t-0 border-gray-700 rounded-b cursor-ns-resize flex items-center justify-center group select-none mb-2"
                @mousedown.prevent="startResize"
            >
                <div class="w-8 h-px bg-gray-600 group-hover:bg-indigo-500 transition-colors" />
            </div>

            <!-- GraphQL variables -->
            <template v-if="bodyType === 'graphql'">
                <button
                    class="text-xs text-left text-gray-500 hover:text-gray-300 transition-colors mb-1 flex items-center gap-1"
                    @click="showGqlVars = !showGqlVars"
                >
                    <svg
                        class="w-2.5 h-2.5 transition-transform shrink-0"
                        :class="showGqlVars ? '' : '-rotate-90'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                    Variables
                </button>

                <div
                    v-if="showGqlVars"
                    class="relative rounded border overflow-hidden transition-colors focus-within:border-indigo-500 border-gray-700 h-24"
                >
                    <pre
                        ref="gqlVarsPre"
                        v-show="gqlVariables"
                        class="editor-pre absolute inset-0 m-0 overflow-auto pointer-events-none select-none"
                        v-html="highlightedGqlVars"
                    />
                    <textarea
                        v-model="gqlVariables"
                        placeholder='{ "id": 1 }'
                        class="editor-textarea absolute inset-0 w-full h-full"
                        spellcheck="false"
                        autocomplete="off"
                        @scroll="syncGqlVarsScroll"
                    />
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
/*
 * Both layers must be pixel-identical so the invisible textarea text
 * lines up with the highlighted <pre> text at every scroll position.
 * Use !important to prevent Tailwind or browser defaults from interfering.
 */
.editor-pre,
.editor-textarea {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
                 "Liberation Mono", "Courier New", monospace !important;
    font-size:   12px !important;
    line-height: 20px !important;
    padding:     8px 12px !important;
    white-space: pre-wrap !important;
    word-wrap:   break-word !important;
    overflow-wrap: break-word !important;
    tab-size:    4;
    -moz-tab-size: 4;
    box-sizing:  border-box;
    margin:      0;
    border:      none !important;
    outline:     none;
}

/* Pre: opaque background + default text colour for non-highlighted content */
.editor-pre {
    background: rgb(17 24 39); /* gray-900 — must be opaque */
    color: #d1d5db; /* gray-300 */
}

/* Textarea: transparent so the <pre> underneath shows through */
.editor-textarea {
    background:              transparent !important;
    color:                   transparent !important;
    -webkit-text-fill-color: transparent !important;
    caret-color:             #d1d5db;
    resize:                  none;
}

/* Prism JSON */
.editor-pre :deep(.token.property)     { color: #7dd3fc; }
.editor-pre :deep(.token.string)       { color: #86efac; }
.editor-pre :deep(.token.number)       { color: #fdba74; }
.editor-pre :deep(.token.boolean)      { color: #c084fc; }
.editor-pre :deep(.token.null.keyword) { color: #94a3b8; }
.editor-pre :deep(.token.punctuation)  { color: #4b5563; }

/* Prism GraphQL / SQL — keywords, functions, operators, comments */
.editor-pre :deep(.token.keyword)      { color: #c084fc; }
.editor-pre :deep(.token.function)     { color: #7dd3fc; }
.editor-pre :deep(.token.variable)     { color: #fdba74; }
.editor-pre :deep(.token.operator)     { color: #4b5563; }
.editor-pre :deep(.token.comment)      { color: #4b5563; font-style: italic; }
</style>
