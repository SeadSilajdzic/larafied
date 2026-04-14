<script setup lang="ts">
import { ref, computed } from 'vue'
import Prism from 'prismjs'
import 'prismjs/components/prism-json'
import 'prismjs/components/prism-sql'
import 'prismjs/components/prism-markup'
import type { AssertionResult, ProxyResponse, SqlResult } from '../types'

const props = defineProps<{
    response:         ProxyResponse | null
    error:            string | null
    sending:          boolean
    sqlResult:        SqlResult | null
    sqlError:         string | null
    scriptError:      string | null
    assertionResults: AssertionResult[]
}>()

type ResponseTab = 'body' | 'headers' | 'queries' | 'tests'

const passedCount  = computed(() => props.assertionResults.filter(r => r.passed).length)
const failedCount  = computed(() => props.assertionResults.filter(r => !r.passed).length)
const hasTestRun   = computed(() => props.assertionResults.length > 0)

const activeTab = ref<ResponseTab>('body')

// ─── Proxy response helpers ───────────────────────────────────────────────────

const statusClass = computed(() => {
    const s = props.response?.status ?? 0
    if (s >= 200 && s < 300) return 'bg-emerald-900/60 text-emerald-300 border-emerald-700'
    if (s >= 300 && s < 400) return 'bg-blue-900/60 text-blue-300 border-blue-700'
    if (s >= 400 && s < 500) return 'bg-amber-900/60 text-amber-300 border-amber-700'
    return 'bg-red-900/60 text-red-300 border-red-700'
})

const isJson = computed(() => {
    if (!props.response) return false
    const ct = props.response.content_type.toLowerCase()
    return ct.includes('application/json') || ct.includes('text/json')
})

const isXml = computed(() => {
    if (!props.response) return false
    const ct = props.response.content_type.toLowerCase()
    return ct.includes('xml')
})

const isHtml = computed(() => {
    if (!props.response) return false
    const ct = props.response.content_type.toLowerCase()
    return ct.includes('text/html')
})

function prettyXml(xml: string): string {
    try {
        const parser = new DOMParser()
        const doc = parser.parseFromString(xml, 'application/xml')
        const err = doc.querySelector('parsererror')
        if (err) return xml

        const serializer = new XMLSerializer()
        const raw = serializer.serializeToString(doc)

        // Indent
        let indent = 0
        return raw
            .replace(/>\s*</g, '><')
            .split('<')
            .filter(Boolean)
            .map(part => {
                const tag = '<' + part
                if (/^<\//.test(tag)) indent = Math.max(0, indent - 1)
                const line = '  '.repeat(indent) + tag
                if (!/\/>$/.test(tag) && !/<\//.test(tag) && !tag.startsWith('<?')) indent++
                return line
            })
            .join('\n')
    } catch {
        return xml
    }
}

const prettyBody = computed(() => {
    if (!props.response) return ''
    if (isJson.value) {
        try {
            return JSON.stringify(JSON.parse(props.response.body), null, 2)
        } catch {
            return props.response.body
        }
    }
    if (isXml.value) return prettyXml(props.response.body)
    return props.response.body
})

const highlightedBody = computed(() => {
    if (!prettyBody.value) return ''
    if (isJson.value) {
        try { return Prism.highlight(prettyBody.value, Prism.languages.json, 'json') } catch { /* fallthrough */ }
    }
    if (isXml.value || isHtml.value) {
        try { return Prism.highlight(prettyBody.value, Prism.languages.markup, 'markup') } catch { /* fallthrough */ }
    }
    return ''
})

const responseHeaders = computed(() => {
    if (!props.response) return []
    return Object.entries(props.response.headers).map(([key, value]) => ({ key, value }))
})

const hasQueries = computed(() => (props.response?.queries?.length ?? 0) > 0)

const totalQueryTime = computed(() =>
    (props.response?.queries ?? []).reduce((sum, q) => sum + q.time_ms, 0).toFixed(2),
)

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    return `${(bytes / 1024).toFixed(1)} KB`
}

const HTTP_STATUS: Record<number, string> = {
    200: 'OK', 201: 'Created', 204: 'No Content', 301: 'Moved Permanently',
    302: 'Found', 304: 'Not Modified', 400: 'Bad Request', 401: 'Unauthorized',
    403: 'Forbidden', 404: 'Not Found', 405: 'Method Not Allowed',
    409: 'Conflict', 422: 'Unprocessable Entity', 429: 'Too Many Requests',
    500: 'Internal Server Error', 502: 'Bad Gateway', 503: 'Service Unavailable',
}

function statusText(status: number): string {
    return HTTP_STATUS[status] ?? ''
}

const copied = ref(false)

async function copyBody(): Promise<void> {
    if (!prettyBody.value) return
    await navigator.clipboard?.writeText(prettyBody.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
}

// ─── SQL result helpers ────────────────────────────────────────────────────────

const sqlColumns = computed(() => {
    if (!props.sqlResult?.rows?.length) return []
    return Object.keys(props.sqlResult.rows[0])
})
</script>

<template>
    <div class="flex flex-col flex-1 overflow-hidden">

        <!-- Empty state -->
        <div
            v-if="!response && !sqlResult && !error && !sqlError && !scriptError && !sending"
            class="flex-1 flex items-center justify-center text-gray-700 text-xs"
        >
            Send a request to see the response
        </div>

        <!-- Loading state -->
        <div
            v-else-if="sending"
            class="flex-1 flex items-center justify-center text-gray-500 text-xs"
        >
            <span class="animate-pulse">Waiting for response…</span>
        </div>

        <!-- Pre-request script error -->
        <div
            v-else-if="scriptError && !response && !sqlResult"
            class="flex-1 flex flex-col items-center justify-center gap-2 px-6"
        >
            <p class="text-xs text-amber-400 font-medium">Pre-request script error</p>
            <p class="text-red-400 text-xs font-mono text-center whitespace-pre-wrap">{{ scriptError }}</p>
        </div>

        <!-- SQL error -->
        <div
            v-else-if="sqlError && !sqlResult && !response"
            class="flex-1 flex flex-col items-center justify-center gap-1 px-6"
        >
            <p class="text-red-400 text-xs font-mono text-center whitespace-pre-wrap">{{ sqlError }}</p>
        </div>

        <!-- Proxy error -->
        <div
            v-else-if="error && !response && !sqlResult"
            class="flex-1 flex flex-col items-center justify-center gap-1 px-6"
        >
            <p class="text-red-400 text-xs font-mono text-center">{{ error }}</p>
        </div>

        <!-- SQL result -->
        <template v-else-if="sqlResult && !response">
            <!-- Meta bar -->
            <div class="flex items-center gap-3 px-3 py-2 border-b border-gray-800 shrink-0">
                <span class="text-xs font-semibold font-mono px-2 py-0.5 rounded border bg-emerald-900/60 text-emerald-300 border-emerald-700">
                    {{ sqlResult.count }} row{{ sqlResult.count !== 1 ? 's' : '' }}
                </span>
                <span class="text-xs text-gray-500">{{ sqlResult.duration_ms }} ms</span>
                <span class="text-xs text-gray-600 truncate ml-auto font-mono">{{ sqlResult.connection }}</span>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-auto">
                <div v-if="sqlResult.count === 0" class="flex items-center justify-center h-full text-gray-600 text-xs">
                    Query returned no rows.
                </div>
                <table v-else class="w-full text-xs border-collapse">
                    <thead class="sticky top-0 bg-gray-900 z-10">
                        <tr>
                            <th
                                v-for="col in sqlColumns"
                                :key="col"
                                class="px-3 py-1.5 text-left font-mono text-indigo-400 border-b border-gray-800 whitespace-nowrap"
                            >
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, i) in sqlResult.rows"
                            :key="i"
                            class="border-b border-gray-800/50 hover:bg-gray-800/30"
                        >
                            <td
                                v-for="col in sqlColumns"
                                :key="col"
                                class="px-3 py-1.5 font-mono text-gray-300 whitespace-nowrap max-w-xs truncate"
                                :title="String(row[col] ?? '')"
                            >
                                <span v-if="row[col] === null" class="text-gray-600 italic">null</span>
                                <span v-else>{{ row[col] }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Proxy response -->
        <template v-else-if="response">
            <!-- Meta bar -->
            <div class="flex items-center gap-3 px-3 py-2 border-b border-gray-800 shrink-0">
                <span
                    class="text-xs font-semibold font-mono px-2 py-0.5 rounded border"
                    :class="statusClass"
                >
                    {{ response.status }}
                </span>
                <span v-if="statusText(response.status)" class="text-xs text-gray-500">{{ statusText(response.status) }}</span>
                <span class="text-gray-700 text-xs">·</span>
                <span class="text-xs text-gray-500 tabular-nums">{{ response.duration_ms }} ms</span>
                <span class="text-xs text-gray-600 tabular-nums">{{ formatSize(response.size) }}</span>
                <span class="text-xs text-gray-700 truncate ml-auto">{{ response.content_type }}</span>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-800 px-3 shrink-0">
                <button
                    class="py-2 px-1 mr-4 text-xs font-medium capitalize transition-colors border-b-2 -mb-px"
                    :class="activeTab === 'body'
                        ? 'border-indigo-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = 'body'"
                >
                    Body
                </button>
                <button
                    class="py-2 px-1 mr-4 text-xs font-medium capitalize transition-colors border-b-2 -mb-px"
                    :class="activeTab === 'headers'
                        ? 'border-indigo-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = 'headers'"
                >
                    Headers
                    <span class="ml-1 text-gray-600">({{ responseHeaders.length }})</span>
                </button>
                <button
                    v-if="hasQueries"
                    class="py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px"
                    :class="activeTab === 'queries'
                        ? 'border-indigo-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = 'queries'"
                >
                    Queries
                    <span
                        class="ml-1 px-1 rounded text-xs"
                        :class="activeTab === 'queries' ? 'text-indigo-400' : 'text-gray-600'"
                    >
                        {{ response.queries.length }}
                    </span>
                </button>

                <!-- Tests tab (shown when assertions were run) -->
                <button
                    v-if="hasTestRun"
                    class="flex items-center gap-1.5 py-2 px-1 mr-4 text-xs font-medium transition-colors border-b-2 -mb-px"
                    :class="activeTab === 'tests'
                        ? 'border-emerald-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = 'tests'"
                >
                    Tests
                    <span v-if="failedCount" class="text-red-400">({{ failedCount }} failed)</span>
                    <span v-else class="text-emerald-400">({{ passedCount }} passed)</span>
                </button>
            </div>

            <!-- Body tab -->
            <div v-if="activeTab === 'body'" class="flex-1 overflow-auto relative group/body">
                <!-- Copy button -->
                <button
                    v-if="prettyBody"
                    class="absolute top-2 right-3 z-10 px-2 py-0.5 rounded text-xs transition-all opacity-0 group-hover/body:opacity-100 focus:opacity-100"
                    :class="copied
                        ? 'bg-emerald-900/60 text-emerald-400 border border-emerald-700'
                        : 'bg-gray-800 text-gray-400 border border-gray-700 hover:text-white hover:border-gray-600'"
                    @click="copyBody"
                >
                    {{ copied ? 'Copied' : 'Copy' }}
                </button>
                <pre
                    class="p-4 text-xs font-mono leading-relaxed whitespace-pre-wrap break-words text-gray-300"
                    v-html="highlightedBody || prettyBody || '(empty body)'"
                />
            </div>

            <!-- Headers tab -->
            <div v-else-if="activeTab === 'headers'" class="flex-1 overflow-auto">
                <table class="w-full text-xs">
                    <tbody>
                        <tr
                            v-for="h in responseHeaders"
                            :key="h.key"
                            class="border-b border-gray-800/50 hover:bg-gray-800/30"
                        >
                            <td class="px-3 py-1.5 font-mono text-indigo-400 w-1/3 align-top">{{ h.key }}</td>
                            <td class="px-3 py-1.5 font-mono text-gray-300 break-all">{{ h.value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Queries tab -->
            <div v-else-if="activeTab === 'queries'" class="flex-1 overflow-auto">
                <!-- Summary bar -->
                <div class="px-3 py-1.5 border-b border-gray-800 flex items-center gap-3 text-xs text-gray-500 shrink-0">
                    <span>{{ response.queries.length }} quer{{ response.queries.length === 1 ? 'y' : 'ies' }}</span>
                    <span>{{ totalQueryTime }} ms total</span>
                </div>

                <div
                    v-for="(q, i) in response.queries"
                    :key="i"
                    class="border-b border-gray-800/50 px-3 py-2"
                >
                    <!-- Query SQL -->
                    <pre
                        class="query-pre text-xs font-mono text-gray-300 whitespace-pre-wrap break-words mb-1"
                        v-html="Prism.highlight(q.sql, Prism.languages.sql, 'sql')"
                    />

                    <!-- Bindings + time -->
                    <div class="flex items-start gap-3 mt-1">
                        <div v-if="q.bindings.length" class="flex-1 flex flex-wrap gap-1">
                            <span
                                v-for="(b, bi) in q.bindings"
                                :key="bi"
                                class="px-1.5 py-0.5 rounded bg-gray-800 text-gray-400 text-xs font-mono"
                            >
                                {{ b }}
                            </span>
                        </div>
                        <span
                            class="ml-auto text-xs font-mono shrink-0"
                            :class="q.time_ms > 100 ? 'text-amber-400' : q.time_ms > 300 ? 'text-red-400' : 'text-gray-600'"
                        >
                            {{ q.time_ms }} ms
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tests tab -->
            <div v-else-if="activeTab === 'tests'" class="flex-1 overflow-auto">
                <div class="px-3 py-1.5 border-b border-gray-800 flex items-center gap-3 text-xs text-gray-500">
                    <span class="text-emerald-400">{{ passedCount }} passed</span>
                    <span v-if="failedCount" class="text-red-400">{{ failedCount }} failed</span>
                </div>

                <div
                    v-for="(result, i) in assertionResults"
                    :key="i"
                    class="flex items-start gap-2 border-b border-gray-800/50 px-3 py-2 text-xs"
                >
                    <span
                        class="shrink-0 font-bold mt-0.5"
                        :class="result.passed ? 'text-emerald-400' : 'text-red-400'"
                    >{{ result.passed ? '✓' : '✗' }}</span>
                    <div class="flex-1 min-w-0">
                        <span class="font-mono text-gray-300">
                            {{ result.assertion.type.replace(/_/g, ' ') }}
                            <span v-if="result.assertion.key" class="text-indigo-400"> {{ result.assertion.key }}</span>
                            <span class="text-gray-500"> = </span>
                            <span class="text-amber-300">{{ result.assertion.value }}</span>
                        </span>
                        <p v-if="!result.passed" class="text-red-400/80 mt-0.5 truncate">{{ result.message }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* Prism JSON token colours — response viewer */
pre :deep(.token.property)    { color: #7dd3fc; }
pre :deep(.token.string)      { color: #86efac; }
pre :deep(.token.number)      { color: #fca5a5; }
pre :deep(.token.boolean)     { color: #c084fc; }
pre :deep(.token.null.keyword){ color: #94a3b8; }
pre :deep(.token.punctuation) { color: #4b5563; }
pre :deep(.token.operator)    { color: #4b5563; }

/* SQL in query log */
.query-pre :deep(.token.keyword)  { color: #c084fc; }
.query-pre :deep(.token.function) { color: #7dd3fc; }
.query-pre :deep(.token.string)   { color: #86efac; }
.query-pre :deep(.token.number)   { color: #fca5a5; }
.query-pre :deep(.token.operator) { color: #4b5563; }
.query-pre :deep(.token.comment)  { color: #4b5563; font-style: italic; }
</style>
