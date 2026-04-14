<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useNetworkMonitor } from '../composables/useNetworkMonitor'
import type { NetworkEvent } from '../composables/useNetworkMonitor'

const props = defineProps<{ active: boolean }>()
const emit  = defineEmits<{ loadRequest: [data: object] }>()

const {
    events, total, enabled, checkConfig, start, stop, clear,
} = useNetworkMonitor()

// Start/stop polling based on whether this panel is expanded
watch(() => props.active, async (on) => {
    if (on) {
        await checkConfig()
        if (enabled.value) start()
    } else {
        stop()
    }
}, { immediate: true })

const selected = ref<NetworkEvent | null>(null)
const filter   = ref('')
const paused   = ref(false)

watch(paused, (p) => p ? stop() : start())

const filtered = computed(() =>
    events.value.filter(e =>
        !filter.value || e.path.includes(filter.value) || e.method.includes(filter.value.toUpperCase()),
    ),
)

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

function statusColor(status: number | null): string {
    if (status === null) return 'text-gray-500'
    if (status < 300)   return 'text-emerald-400'
    if (status < 400)   return 'text-amber-400'
    return 'text-red-400'
}

function loadIntoBuilder(event: NetworkEvent): void {
    const data: Record<string, unknown> = {
        method:  event.method,
        url:     event.query ? `${event.path}?${event.query}` : event.path,
        headers: event.req_headers,
        body:    event.req_body ?? '',
    }
    emit('loadRequest', data)
}

function tryParseJson(s: string | null): string {
    if (!s) return ''
    try {
        return JSON.stringify(JSON.parse(s), null, 2)
    } catch {
        return s
    }
}

function formatDuration(ms: number | null): string {
    if (ms === null) return '—'
    if (ms < 1000) return `${ms}ms`
    return `${(ms / 1000).toFixed(2)}s`
}

function formatTime(ts: number): string {
    return new Date(ts * 1000).toLocaleTimeString()
}
</script>

<template>
    <div class="flex flex-col h-full text-xs">

        <!-- Disabled state -->
        <div
            v-if="!enabled"
            class="flex-1 flex flex-col items-center justify-center gap-2 px-4 text-center text-gray-600"
        >
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="leading-relaxed">
                Network monitor disabled.<br/>
                Set <code class="text-indigo-400">LARAFIED_NETWORK_MONITOR=true</code> in <code class="text-indigo-400">.env</code>
                and add <code class="text-indigo-400">NetworkMonitorMiddleware</code> to your app.
            </p>
        </div>

        <template v-else>
            <!-- Toolbar -->
            <div class="px-3 py-2 border-b border-gray-800 flex items-center gap-2">
                <input
                    v-model="filter"
                    type="text"
                    placeholder="Filter by path or method…"
                    class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                />
                <button
                    class="shrink-0 text-xs transition-colors"
                    :class="paused ? 'text-amber-400 hover:text-amber-300' : 'text-gray-500 hover:text-gray-300'"
                    :title="paused ? 'Resume capture' : 'Pause capture'"
                    @click="paused = !paused"
                >
                    <svg v-if="paused" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button
                    class="shrink-0 text-xs text-gray-500 hover:text-red-400 transition-colors"
                    title="Clear all events"
                    @click="clear"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>

            <div class="flex flex-1 overflow-hidden min-h-0">

                <!-- Event list -->
                <div class="w-full overflow-y-auto" :class="selected ? 'hidden' : ''">
                    <div
                        v-if="filtered.length === 0"
                        class="flex items-center justify-center h-20 text-gray-700 italic"
                    >
                        {{ events.length === 0 ? 'Waiting for requests…' : 'No matches.' }}
                    </div>

                    <div
                        v-for="event in filtered"
                        :key="event.id"
                        class="flex items-center gap-2 px-3 py-1.5 border-b border-gray-800/50 hover:bg-gray-800/40 cursor-pointer"
                        :class="selected?.id === event.id ? 'bg-gray-800/60' : ''"
                        @click="selected = event"
                    >
                        <span
                            class="font-mono font-semibold w-12 shrink-0"
                            :class="METHOD_COLORS[event.method] ?? 'text-gray-400'"
                        >{{ event.method }}</span>
                        <span
                            class="font-semibold w-10 shrink-0 tabular-nums"
                            :class="statusColor(event.status)"
                        >{{ event.status ?? '?' }}</span>
                        <span class="flex-1 text-gray-300 truncate font-mono">
                            {{ event.path }}<span v-if="event.query" class="text-gray-600">?{{ event.query }}</span>
                        </span>
                        <span class="shrink-0 text-gray-600 tabular-nums">{{ formatDuration(event.duration_ms) }}</span>
                        <span class="shrink-0 text-gray-700 tabular-nums">{{ formatTime(event.created_at) }}</span>
                    </div>
                </div>

                <!-- Detail panel -->
                <div v-if="selected" class="flex flex-col w-full overflow-hidden min-h-0">
                    <!-- Header -->
                    <div class="px-3 py-2 border-b border-gray-800 flex items-center gap-2 shrink-0">
                        <button
                            class="text-gray-500 hover:text-gray-300"
                            @click="selected = null"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span
                            class="font-mono font-semibold"
                            :class="METHOD_COLORS[selected.method] ?? 'text-gray-400'"
                        >{{ selected.method }}</span>
                        <span class="flex-1 text-gray-300 font-mono truncate">{{ selected.path }}</span>
                        <span :class="statusColor(selected.status)" class="font-semibold">{{ selected.status }}</span>
                        <span class="text-gray-600">{{ formatDuration(selected.duration_ms) }}</span>
                        <button
                            class="ml-1 text-indigo-400 hover:text-indigo-300 transition-colors"
                            title="Load into RequestBuilder"
                            @click="loadIntoBuilder(selected)"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs: Request / Response -->
                    <div class="flex-1 overflow-y-auto p-3 space-y-3">

                        <!-- Request -->
                        <div>
                            <p class="text-gray-500 uppercase tracking-wide mb-1" style="font-size:10px">Request headers</p>
                            <pre class="bg-gray-900 rounded p-2 text-gray-300 overflow-x-auto whitespace-pre-wrap break-all" style="font-size:10px">{{ Object.entries(selected.req_headers).map(([k,v]) => `${k}: ${v}`).join('\n') || '(none)' }}</pre>
                        </div>

                        <div v-if="selected.req_body">
                            <p class="text-gray-500 uppercase tracking-wide mb-1" style="font-size:10px">Request body</p>
                            <pre class="bg-gray-900 rounded p-2 text-gray-300 overflow-x-auto whitespace-pre-wrap break-all" style="font-size:10px">{{ tryParseJson(selected.req_body) }}</pre>
                        </div>

                        <!-- Response -->
                        <div>
                            <p class="text-gray-500 uppercase tracking-wide mb-1" style="font-size:10px">Response headers</p>
                            <pre class="bg-gray-900 rounded p-2 text-gray-300 overflow-x-auto whitespace-pre-wrap break-all" style="font-size:10px">{{ Object.entries(selected.res_headers).map(([k,v]) => `${k}: ${v}`).join('\n') || '(none)' }}</pre>
                        </div>

                        <div v-if="selected.res_body">
                            <p class="text-gray-500 uppercase tracking-wide mb-1" style="font-size:10px">Response body</p>
                            <pre class="bg-gray-900 rounded p-2 text-gray-300 overflow-x-auto whitespace-pre-wrap break-all" style="font-size:10px">{{ tryParseJson(selected.res_body) }}</pre>
                        </div>

                    </div>
                </div>

            </div>
        </template>
    </div>
</template>
