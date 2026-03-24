<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ProxyResponse } from '../types'

const props = defineProps<{
    response: ProxyResponse | null
    error: string | null
    sending: boolean
}>()

const activeTab = ref<'body' | 'headers'>('body')

const statusClass = computed(() => {
    const s = props.response?.status ?? 0
    if (s >= 200 && s < 300) return 'bg-emerald-900/60 text-emerald-300 border-emerald-700'
    if (s >= 300 && s < 400) return 'bg-blue-900/60 text-blue-300 border-blue-700'
    if (s >= 400 && s < 500) return 'bg-amber-900/60 text-amber-300 border-amber-700'
    return 'bg-red-900/60 text-red-300 border-red-700'
})

const prettyBody = computed(() => {
    if (!props.response) return ''
    const ct = props.response.content_type.toLowerCase()
    if (ct.includes('application/json') || ct.includes('text/json')) {
        try {
            return JSON.stringify(JSON.parse(props.response.body), null, 2)
        } catch {
            return props.response.body
        }
    }
    return props.response.body
})

const responseHeaders = computed(() => {
    if (!props.response) return []
    return Object.entries(props.response.headers).map(([key, value]) => ({ key, value }))
})

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    return `${(bytes / 1024).toFixed(1)} KB`
}
</script>

<template>
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Empty state -->
        <div
            v-if="!response && !error && !sending"
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

        <!-- Error state -->
        <div
            v-else-if="error && !response"
            class="flex-1 flex flex-col items-center justify-center gap-1 px-6"
        >
            <p class="text-red-400 text-xs font-mono text-center">{{ error }}</p>
        </div>

        <!-- Response -->
        <template v-else-if="response">
            <!-- Meta bar -->
            <div class="flex items-center gap-3 px-3 py-2 border-b border-gray-800 shrink-0">
                <span
                    class="text-xs font-semibold font-mono px-2 py-0.5 rounded border"
                    :class="statusClass"
                >
                    {{ response.status }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ response.duration_ms }} ms
                </span>
                <span class="text-xs text-gray-500">
                    {{ formatSize(response.size) }}
                </span>
                <span class="text-xs text-gray-600 truncate ml-auto">
                    {{ response.content_type }}
                </span>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-800 px-3 shrink-0">
                <button
                    v-for="tab in (['body', 'headers'] as const)"
                    :key="tab"
                    class="py-2 px-1 mr-4 text-xs font-medium capitalize transition-colors border-b-2 -mb-px"
                    :class="activeTab === tab
                        ? 'border-indigo-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                    <span v-if="tab === 'headers'" class="ml-1 text-gray-600">
                        ({{ responseHeaders.length }})
                    </span>
                </button>
            </div>

            <!-- Body tab -->
            <div v-if="activeTab === 'body'" class="flex-1 overflow-auto">
                <pre class="p-3 text-xs font-mono text-gray-300 leading-relaxed whitespace-pre-wrap break-words">{{ prettyBody || '(empty body)' }}</pre>
            </div>

            <!-- Headers tab -->
            <div v-else class="flex-1 overflow-auto">
                <table class="w-full text-xs">
                    <tbody>
                        <tr
                            v-for="h in responseHeaders"
                            :key="h.key"
                            class="border-b border-gray-800/50 hover:bg-gray-800/30"
                        >
                            <td class="px-3 py-1.5 font-mono text-indigo-400 w-1/3 align-top">
                                {{ h.key }}
                            </td>
                            <td class="px-3 py-1.5 font-mono text-gray-300 break-all">
                                {{ h.value }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
