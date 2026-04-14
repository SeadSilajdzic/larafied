<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import CollectionsSidebar from './components/CollectionsSidebar.vue'
import EnvironmentsPanel from './components/EnvironmentsPanel.vue'
import HistorySidebar from './components/HistorySidebar.vue'
import LicenseBadge from './components/LicenseBadge.vue'
import LicenseModal from './components/LicenseModal.vue'
import RequestBuilder from './components/RequestBuilder.vue'
import ResponseViewer from './components/ResponseViewer.vue'
import RouteSidebar from './components/RouteSidebar.vue'
import NetworkMonitor from './components/NetworkMonitor.vue'
import WebSocketPanel from './components/WebSocketPanel.vue'
import { useApi } from './composables/useApi'
import { useEnvironments } from './composables/useEnvironments'
import { useLicense } from './composables/useLicense'
import { useProxy } from './composables/useProxy'
import { useSync } from './composables/useSync'
import { interpolate } from './interpolate'
import { runPreRequestScript } from './preRequest'
import { evaluateAssertions } from './utilities/evaluateAssertions'
import type { ActiveRequest, AssertionResult, LarafiedConfig, RequestData, SqlResult } from './types'
import { defaultAuth } from './types'

const props = defineProps<{ config: LarafiedConfig }>()

type SidebarSection = 'routes' | 'collections' | 'environments' | 'history' | 'network' | 'websocket'
type LayoutMode     = 'stacked' | 'side-by-side'
type BodyType       = 'json' | 'raw' | 'graphql' | 'sql'

const expandedSections = ref<Set<SidebarSection>>(new Set(['routes']))
const showLicense      = ref(false)
const layoutMode       = ref<LayoutMode>('stacked')

function toggleSection(id: SidebarSection): void {
    if (expandedSections.value.has(id)) {
        expandedSections.value.delete(id)
    } else {
        expandedSections.value.add(id)
    }
    // Trigger reactivity
    expandedSections.value = new Set(expandedSections.value)
}

const request = reactive<ActiveRequest>({
    method:           'GET',
    url:              '',
    headers:          [],
    body:             '',
    auth:             defaultAuth(),
    preRequestScript: '',
    assertions:       [],
})

const assertionResults = ref<AssertionResult[]>([])

const bodyType   = ref<BodyType>('json')
const queryLog   = ref(false)

const sqlResult  = ref<SqlResult | null>(null)
const sqlError    = ref<string | null>(null)
const sqlSending  = ref(false)
const scriptError = ref<string | null>(null)

const { response, sending, error: proxyError, send, clear: clearProxy } = useProxy()
const { tier, features, graceWarning, loading: licenseLoading, error: licenseError, fetchLicense, activate } = useLicense()
const { active: activeEnvironment, fetchEnvironments } = useEnvironments()
const { state: syncState, message: syncMessage, push: syncPush, pull: syncPull } = useSync()

const canSync = computed(() => features.value.includes('cloud_sync'))

const isSending = computed(() => sending.value || sqlSending.value)

/** Flat map of env variable key → value for the active environment. */
const envVars = computed<Record<string, string>>(() => {
    if (!activeEnvironment.value) return {}
    return Object.fromEntries(
        activeEnvironment.value.variables.map(v => [v.key, v.value]),
    )
})

function handleGlobalKeydown(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && e.key === 's' && !e.shiftKey) {
        e.preventDefault()
        // Expand the Collections sidebar section so the user can save the current request
        expandedSections.value = new Set([...expandedSections.value, 'collections'])
    }
}

onMounted(() => {
    fetchLicense()
    fetchEnvironments()
    document.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown)
})

async function handleActivate(key: string): Promise<void> {
    await activate(key, window.location.hostname)
    if (!licenseError.value) {
        showLicense.value = false
    }
}

function loadRequest(data: RequestData): void {
    request.method  = data.method
    request.url     = data.url ?? ''
    request.headers = Object.entries(data.headers ?? {}).map(([key, value]) => ({
        key,
        value,
        enabled: true,
    }))
    request.body             = typeof data.body === 'string' ? data.body : ''
    request.auth             = data.auth ? { ...data.auth } : defaultAuth()
    request.preRequestScript = data.preRequestScript ?? ''
    request.assertions       = data.assertions ? [...data.assertions] : []
    assertionResults.value   = []
}

function interpolateActive(text: string): string {
    return interpolate(text, envVars.value)
}

function resolveUrl(url: string): string {
    if (!url) return url
    if (url.startsWith('http://') || url.startsWith('https://')) return url
    const path = url.startsWith('/') ? url : `/${url}`
    return `${window.location.origin}${path}`
}

function buildHeaders(): Record<string, string> {
    const headers: Record<string, string> = {}
    for (const h of request.headers) {
        if (h.enabled && h.key.trim()) {
            headers[h.key.trim()] = interpolateActive(h.value)
        }
    }

    // Inject auth header (does not override a manually set Authorization header)
    const auth = request.auth
    if (auth.type === 'bearer' && auth.token) {
        const token = interpolateActive(auth.token)
        if (token && !Object.keys(headers).some(k => k.toLowerCase() === 'authorization')) {
            headers['Authorization'] = `Bearer ${token}`
        }
    } else if (auth.type === 'basic' && (auth.username || auth.password)) {
        if (!Object.keys(headers).some(k => k.toLowerCase() === 'authorization')) {
            const credentials = btoa(`${interpolateActive(auth.username ?? '')}:${interpolateActive(auth.password ?? '')}`)
            headers['Authorization'] = `Basic ${credentials}`
        }
    } else if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'header') {
        const headerName = interpolateActive(auth.key)
        if (headerName && !Object.prototype.hasOwnProperty.call(headers, headerName)) {
            headers[headerName] = interpolateActive(auth.value)
        }
    }

    return headers
}

/** Returns query params to append to the URL from API key auth (addTo=query). */
function buildAuthQueryParams(): Record<string, string> {
    const auth = request.auth
    if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'query') {
        return { [interpolateActive(auth.key)]: interpolateActive(auth.value) }
    }
    return {}
}

function appendQueryParams(url: string, params: Record<string, string>): string {
    if (!Object.keys(params).length) return url
    const sep    = url.includes('?') ? '&' : '?'
    const query  = Object.entries(params).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')
    return `${url}${sep}${query}`
}

async function handleSend(): Promise<void> {
    sqlResult.value   = null
    sqlError.value    = null
    scriptError.value = null

    // Run pre-request script (Pro feature) — mutates a copy of the request state
    let scriptMethod  = request.method
    let scriptUrl     = interpolateActive(request.url)
    let scriptHeaders = Object.fromEntries(
        request.headers
            .filter(h => h.enabled && h.key.trim())
            .map(h => [h.key.trim(), interpolateActive(h.value)]),
    )
    let scriptBody    = request.body.trim()
    let updatedEnvVars = envVars.value

    if (features.value.includes('pre_request_scripts') && request.preRequestScript.trim()) {
        const scriptResult = runPreRequestScript(request.preRequestScript, {
            method:  scriptMethod,
            url:     scriptUrl,
            headers: scriptHeaders,
            body:    scriptBody,
            envVars: { ...envVars.value },
        })

        if (scriptResult.error) {
            scriptError.value = scriptResult.error
            return
        }

        scriptMethod  = scriptResult.method
        scriptUrl     = scriptResult.url
        scriptHeaders = scriptResult.headers
        scriptBody    = scriptResult.body
        updatedEnvVars = scriptResult.envVars
    }

    // Merge auth into headers (from the post-script state)
    const auth = request.auth
    if (auth.type === 'bearer' && auth.token) {
        const token = interpolate(auth.token, updatedEnvVars)
        if (token && !Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'authorization')) {
            scriptHeaders['Authorization'] = `Bearer ${token}`
        }
    } else if (auth.type === 'basic' && (auth.username || auth.password)) {
        if (!Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'authorization')) {
            const creds = btoa(`${interpolate(auth.username ?? '', updatedEnvVars)}:${interpolate(auth.password ?? '', updatedEnvVars)}`)
            scriptHeaders['Authorization'] = `Basic ${creds}`
        }
    } else if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'header') {
        const headerName = interpolate(auth.key, updatedEnvVars)
        if (headerName && !Object.prototype.hasOwnProperty.call(scriptHeaders, headerName)) {
            scriptHeaders[headerName] = interpolate(auth.value, updatedEnvVars)
        }
    }

    // API key in query
    if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'query') {
        const qParams = { [interpolate(auth.key, updatedEnvVars)]: interpolate(auth.value, updatedEnvVars) }
        scriptUrl = appendQueryParams(resolveUrl(scriptUrl), qParams)
    } else {
        scriptUrl = resolveUrl(scriptUrl)
    }

    // Auto-set Content-Type for JSON bodies
    if (scriptBody && !Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'content-type')) {
        try {
            JSON.parse(scriptBody)
            scriptHeaders['Content-Type'] = 'application/json'
        } catch { /* not JSON */ }
    }

    await send({
        method:  scriptMethod,
        url:     scriptUrl,
        headers: Object.keys(scriptHeaders).length > 0 ? scriptHeaders : undefined,
        body:    scriptBody || undefined,
        debug:   queryLog.value,
    })

    if (response.value && request.assertions.length) {
        assertionResults.value = evaluateAssertions(request.assertions, response.value)
    } else {
        assertionResults.value = []
    }
}

async function handleSendGraphql(formattedBody: string): Promise<void> {
    sqlResult.value   = null
    sqlError.value    = null
    scriptError.value = null

    let scriptUrl     = interpolateActive(request.url)
    let scriptHeaders = Object.fromEntries(
        request.headers
            .filter(h => h.enabled && h.key.trim())
            .map(h => [h.key.trim(), interpolateActive(h.value)]),
    )
    let updatedEnvVars = envVars.value

    if (features.value.includes('pre_request_scripts') && request.preRequestScript.trim()) {
        const scriptResult = runPreRequestScript(request.preRequestScript, {
            method:  'POST',
            url:     scriptUrl,
            headers: scriptHeaders,
            body:    formattedBody,
            envVars: { ...envVars.value },
        })

        if (scriptResult.error) {
            scriptError.value = scriptResult.error
            return
        }

        scriptUrl      = scriptResult.url
        scriptHeaders  = scriptResult.headers
        updatedEnvVars = scriptResult.envVars
    }

    // Inject auth
    const auth = request.auth
    if (auth.type === 'bearer' && auth.token) {
        const token = interpolate(auth.token, updatedEnvVars)
        if (token && !Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'authorization')) {
            scriptHeaders['Authorization'] = `Bearer ${token}`
        }
    } else if (auth.type === 'basic' && (auth.username || auth.password)) {
        if (!Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'authorization')) {
            const creds = btoa(`${interpolate(auth.username ?? '', updatedEnvVars)}:${interpolate(auth.password ?? '', updatedEnvVars)}`)
            scriptHeaders['Authorization'] = `Basic ${creds}`
        }
    } else if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'header') {
        const headerName = interpolate(auth.key, updatedEnvVars)
        if (headerName && !Object.prototype.hasOwnProperty.call(scriptHeaders, headerName)) {
            scriptHeaders[headerName] = interpolate(auth.value, updatedEnvVars)
        }
    }

    if (auth.type === 'apikey' && auth.key && auth.value && auth.in === 'query') {
        const qParams = { [interpolate(auth.key, updatedEnvVars)]: interpolate(auth.value, updatedEnvVars) }
        scriptUrl = appendQueryParams(resolveUrl(scriptUrl), qParams)
    } else {
        scriptUrl = resolveUrl(scriptUrl)
    }

    if (!Object.keys(scriptHeaders).some(k => k.toLowerCase() === 'content-type')) {
        scriptHeaders['Content-Type'] = 'application/json'
    }

    await send({
        method:  'POST',
        url:     scriptUrl,
        headers: scriptHeaders,
        body:    formattedBody,
        debug:   queryLog.value,
    })
}

async function handleSendSql(): Promise<void> {
    clearProxy()
    sqlResult.value  = null
    sqlError.value   = null
    sqlSending.value = true

    try {
        const api    = useApi()
        const result = await api.post<SqlResult | { error: string }>('/sql', { sql: request.body })

        if ('error' in result) {
            sqlError.value = (result as { error: string }).error
        } else {
            sqlResult.value = result as SqlResult
        }
    } catch (e) {
        sqlError.value = e instanceof Error ? e.message : 'Query failed'
    } finally {
        sqlSending.value = false
    }
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-950 text-gray-100">

        <!-- Grace warning banner -->
        <div
            v-if="graceWarning"
            class="fixed top-0 inset-x-0 z-40 bg-amber-900/80 border-b border-amber-700 px-4 py-2 flex items-center justify-between text-xs text-amber-200"
        >
            <span>License validation pending — cloud unreachable. Pro features will revert to free if not resolved soon.</span>
            <button class="ml-4 underline hover:text-white" @click="showLicense = true">Re-activate</button>
        </div>

        <!-- ── Left sidebar ──────────────────────────────────────────────── -->
        <aside
            class="w-72 flex flex-col border-r border-gray-800 shrink-0"
            :class="graceWarning ? 'pt-8' : ''"
        >
            <!-- Branding -->
            <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between shrink-0">
                <span class="font-semibold text-sm text-white">{{ props.config.title }}</span>
                <div class="flex items-center gap-2">
                    <LicenseBadge :tier="tier" />

                    <!-- Cloud sync buttons (team/agency only) -->
                    <template v-if="canSync">
                        <!-- Sync status message -->
                        <span
                            v-if="syncMessage"
                            class="text-xs px-1.5 py-0.5 rounded"
                            :class="{
                                'text-emerald-400 bg-emerald-500/10': syncState === 'success',
                                'text-red-400 bg-red-500/10': syncState === 'error',
                            }"
                        >{{ syncMessage }}</span>

                        <!-- Push -->
                        <button
                            class="text-gray-600 hover:text-sky-400 transition-colors disabled:opacity-40"
                            :disabled="syncState === 'pushing' || syncState === 'pulling'"
                            title="Push workspace to cloud"
                            @click="syncPush"
                        >
                            <!-- spinner when pushing -->
                            <svg v-if="syncState === 'pushing'" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
                            </svg>
                            <!-- upload icon otherwise -->
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </button>

                        <!-- Pull -->
                        <button
                            class="text-gray-600 hover:text-sky-400 transition-colors disabled:opacity-40"
                            :disabled="syncState === 'pushing' || syncState === 'pulling'"
                            title="Pull workspace from cloud"
                            @click="syncPull"
                        >
                            <!-- spinner when pulling -->
                            <svg v-if="syncState === 'pulling'" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
                            </svg>
                            <!-- download icon otherwise -->
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </button>
                    </template>

                    <button
                        class="text-gray-600 hover:text-gray-400 transition-colors"
                        :title="layoutMode === 'stacked' ? 'Side-by-side view' : 'Stacked view'"
                        @click="layoutMode = layoutMode === 'stacked' ? 'side-by-side' : 'stacked'"
                    >
                        <svg v-if="layoutMode === 'stacked'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="8" rx="1" stroke-width="2"/>
                            <rect x="3" y="13" width="18" height="8" rx="1" stroke-width="2"/>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="8" height="18" rx="1" stroke-width="2"/>
                            <rect x="13" y="3" width="8" height="18" rx="1" stroke-width="2"/>
                        </svg>
                    </button>
                    <button
                        class="text-gray-600 hover:text-gray-400 transition-colors"
                        title="License settings"
                        @click="showLicense = true"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Stacked sections -->
            <div class="flex-1 flex flex-col overflow-hidden min-h-0">

                <!-- Routes -->
                <div
                    class="flex flex-col border-b border-gray-800"
                    :class="expandedSections.has('routes') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('routes')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">Routes</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('routes') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('routes')" class="flex-1 overflow-hidden min-h-0">
                        <RouteSidebar @select="loadRequest" />
                    </div>
                </div>

                <!-- Collections -->
                <div
                    class="flex flex-col border-b border-gray-800"
                    :class="expandedSections.has('collections') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('collections')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">Collections</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('collections') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('collections')" class="flex-1 overflow-hidden min-h-0">
                        <CollectionsSidebar
                            :current-request="request"
                            :features="features"
                            @select="loadRequest"
                            @upgrade="showLicense = true"
                        />
                    </div>
                </div>

                <!-- Environments -->
                <div
                    class="flex flex-col border-b border-gray-800"
                    :class="expandedSections.has('environments') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('environments')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">Environments</span>
                        <!-- Active environment indicator -->
                        <span
                            v-if="activeEnvironment"
                            class="text-xs text-indigo-400 bg-indigo-500/10 rounded px-1.5 py-0.5 mr-1.5 truncate max-w-[80px]"
                        >{{ activeEnvironment.name }}</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('environments') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('environments')" class="flex-1 overflow-hidden min-h-0">
                        <EnvironmentsPanel @upgrade="showLicense = true" />
                    </div>
                </div>

                <!-- History -->
                <div
                    class="flex flex-col border-b border-gray-800"
                    :class="expandedSections.has('history') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('history')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">History</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('history') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('history')" class="flex-1 overflow-hidden min-h-0">
                        <HistorySidebar
                            @select="loadRequest"
                            @upgrade="showLicense = true"
                        />
                    </div>
                </div>

                <!-- Network Monitor -->
                <div
                    class="flex flex-col border-b border-gray-800"
                    :class="expandedSections.has('network') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('network')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">Network</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('network') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('network')" class="flex-1 overflow-hidden min-h-0">
                        <NetworkMonitor
                            :active="expandedSections.has('network')"
                            @load-request="(data) => loadRequest(data as import('./types').RequestData)"
                        />
                    </div>
                </div>

                <!-- WebSocket Monitor -->
                <div
                    class="flex flex-col"
                    :class="expandedSections.has('websocket') ? 'flex-1 min-h-0' : 'shrink-0'"
                >
                    <button
                        class="flex items-center w-full px-3 py-2 hover:bg-gray-800/30 transition-colors shrink-0 select-none"
                        @click="toggleSection('websocket')"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-400 flex-1 text-left">WebSockets</span>
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedSections.has('websocket') ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="expandedSections.has('websocket')" class="flex-1 overflow-hidden min-h-0">
                        <WebSocketPanel />
                    </div>
                </div>

            </div>
        </aside>

        <!-- ── Main panel ────────────────────────────────────────────────── -->
        <div
            class="flex flex-1 overflow-hidden"
            :class="[graceWarning ? 'pt-8' : '', layoutMode === 'side-by-side' ? 'flex-row' : 'flex-col']"
        >
            <template v-if="layoutMode === 'stacked'">
                <RequestBuilder
                    :request="request"
                    :sending="isSending"
                    :query-log="queryLog"
                    :features="features"
                    :env-vars="envVars"
                    v-model:body-type="bodyType"
                    @send="handleSend"
                    @send-graphql="handleSendGraphql"
                    @send-sql="handleSendSql"
                    @update:query-log="queryLog = $event"
                    @upgrade="showLicense = true"
                />
                <ResponseViewer
                    :response="response"
                    :error="proxyError"
                    :sending="isSending"
                    :sql-result="sqlResult"
                    :sql-error="sqlError"
                    :script-error="scriptError"
                    :assertion-results="assertionResults"
                />
            </template>

            <template v-else>
                <div class="flex flex-col w-1/2 border-r border-gray-800 overflow-hidden">
                    <RequestBuilder
                        :request="request"
                        :sending="isSending"
                        :query-log="queryLog"
                        :features="features"
                        :env-vars="envVars"
                        v-model:body-type="bodyType"
                        @send="handleSend"
                        @send-graphql="handleSendGraphql"
                        @send-sql="handleSendSql"
                        @update:query-log="queryLog = $event"
                        @upgrade="showLicense = true"
                    />
                </div>
                <div class="flex flex-col w-1/2 overflow-hidden">
                    <ResponseViewer
                        :response="response"
                        :error="proxyError"
                        :sending="isSending"
                        :sql-result="sqlResult"
                        :sql-error="sqlError"
                        :script-error="scriptError"
                    />
                </div>
            </template>
        </div>

        <!-- License modal -->
        <LicenseModal
            v-if="showLicense"
            :loading="licenseLoading"
            :error="licenseError"
            @activate="handleActivate"
            @close="showLicense = false"
        />
    </div>
</template>
