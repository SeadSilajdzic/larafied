<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useHistory } from '../composables/useHistory'
import type { RequestData } from '../types'

const emit = defineEmits<{
    select:  [data: RequestData]
    upgrade: []
}>()

const { entries, loading, locked, fetchHistory, clearHistory } = useHistory()

const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return entries.value
    return entries.value.filter(e =>
        e.url.toLowerCase().includes(q) ||
        e.method.toLowerCase().includes(q) ||
        String(e.status ?? '').includes(q),
    )
})

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

const STATUS_CLASS: (s: number | null) => string = (s) => {
    if (!s)          return 'text-gray-500'
    if (s < 300)     return 'text-emerald-400'
    if (s < 400)     return 'text-blue-400'
    if (s < 500)     return 'text-amber-400'
    return 'text-red-400'
}

function formatTime(ts: number): string {
    const d = new Date(ts * 1000)
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function shortUrl(url: string): string {
    try {
        const u = new URL(url)
        return u.pathname + (u.search ? u.search : '')
    } catch {
        return url
    }
}

function loadEntry(entry: { method: string; url: string; headers: Record<string, string>; body: string | null }): void {
    emit('select', {
        method:  entry.method,
        url:     entry.url,
        headers: entry.headers,
        body:    entry.body ?? '',
    })
}

onMounted(() => fetchHistory())
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- Locked state -->
        <div
            v-if="locked"
            class="flex flex-col items-center justify-center flex-1 gap-3 px-4"
        >
            <svg class="w-6 h-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs text-gray-500 text-center">Request history requires a Pro license.</p>
            <button
                class="px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors"
                @click="emit('upgrade')"
            >
                Upgrade to Pro
            </button>
        </div>

        <!-- Loading state -->
        <div
            v-else-if="loading"
            class="flex flex-1 items-center justify-center text-gray-600 text-xs"
        >
            <span class="animate-pulse">Loading…</span>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!entries.length"
            class="flex flex-1 items-center justify-center text-gray-700 text-xs px-4 text-center"
        >
            No history yet. Send a request to start tracking.
        </div>

        <!-- History list -->
        <template v-else>
            <!-- Search + clear -->
            <div class="px-3 py-2 border-b border-gray-800 flex items-center gap-2 shrink-0">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search history…"
                    class="flex-1 bg-gray-900 text-gray-200 text-xs rounded px-2 py-1 placeholder-gray-600 border border-gray-700 focus:outline-none focus:border-indigo-500 min-w-0"
                />
                <button
                    class="text-xs text-gray-600 hover:text-red-400 transition-colors shrink-0"
                    title="Clear all history"
                    @click="clearHistory"
                >
                    Clear
                </button>
            </div>

            <!-- Empty search result -->
            <div
                v-if="filtered.length === 0"
                class="flex flex-1 items-center justify-center text-gray-700 text-xs px-4 text-center"
            >
                No matches.
            </div>

            <div v-else class="flex-1 overflow-y-auto">
                <button
                    v-for="entry in filtered"
                    :key="entry.id"
                    class="w-full text-left px-3 py-2 border-b border-gray-800/50 hover:bg-gray-800/40 transition-colors group"
                    @click="loadEntry(entry)"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            class="text-xs font-mono font-semibold shrink-0 w-12"
                            :class="METHOD_COLORS[entry.method] ?? 'text-gray-400'"
                        >
                            {{ entry.method }}
                        </span>
                        <span
                            v-if="entry.status"
                            class="text-xs font-mono shrink-0"
                            :class="STATUS_CLASS(entry.status)"
                        >
                            {{ entry.status }}
                        </span>
                        <span class="text-xs font-mono text-gray-400 truncate flex-1">
                            {{ shortUrl(entry.url) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs text-gray-700">{{ formatTime(entry.created_at) }}</span>
                        <span v-if="entry.duration_ms" class="text-xs text-gray-700">{{ entry.duration_ms }} ms</span>
                    </div>
                </button>
            </div>
        </template>
    </div>
</template>
