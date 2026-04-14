<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useWebSocket } from '../composables/useWebSocket'
import type { WsEvent } from '../composables/useWebSocket'

const {
    wsConfig, connState, connError, socketId,
    eventLog, subscribed,
    loadConfig, connect, disconnect,
    sendSubscribe, sendUnsubscribe, clearLog,
} = useWebSocket()

onMounted(loadConfig)

const channelInput = ref('')
const showRaw      = ref(false)
const filterText   = ref('')

function handleSubscribe(): void {
    const ch = channelInput.value.trim()
    if (!ch || !subscribed.value.has(ch) === false) return
    sendSubscribe(ch)
    channelInput.value = ''
}

function handleSubscribeInput(): void {
    const ch = channelInput.value.trim()
    if (!ch) return
    sendSubscribe(ch)
    channelInput.value = ''
}

const filtered = computed(() =>
    eventLog.value.filter(e =>
        !filterText.value ||
        e.channel.includes(filterText.value) ||
        e.event.includes(filterText.value),
    ),
)

const STATE_COLORS: Record<string, string> = {
    connected:    'text-emerald-400',
    connecting:   'text-amber-400',
    disconnected: 'text-gray-500',
    error:        'text-red-400',
}

const STATE_DOT: Record<string, string> = {
    connected:    'bg-emerald-400 animate-pulse',
    connecting:   'bg-amber-400 animate-pulse',
    disconnected: 'bg-gray-600',
    error:        'bg-red-400',
}

function dirIcon(dir: WsEvent['direction']): string {
    if (dir === 'out')    return '↑'
    if (dir === 'system') return '⚙'
    return '↓'
}

function dirColor(dir: WsEvent['direction']): string {
    if (dir === 'out')    return 'text-blue-400'
    if (dir === 'system') return 'text-gray-500'
    return 'text-emerald-400'
}

function formatData(data: unknown): string {
    if (data === null || data === undefined) return '—'
    if (typeof data === 'string') return data
    return JSON.stringify(data, null, 2)
}

function formatTime(ts: number): string {
    return new Date(ts).toLocaleTimeString()
}
</script>

<template>
    <div class="flex flex-col h-full text-xs">

        <!-- Not available -->
        <div
            v-if="wsConfig && !wsConfig.enabled"
            class="flex-1 flex flex-col items-center justify-center gap-2 px-4 text-center text-gray-600"
        >
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
            <p class="leading-relaxed">
                No WebSocket driver configured.<br/>
                Set <code class="text-indigo-400">BROADCAST_DRIVER=reverb</code> or <code class="text-indigo-400">pusher</code>
                in your <code class="text-indigo-400">.env</code>.
            </p>
        </div>

        <template v-else-if="wsConfig">

            <!-- Connection bar -->
            <div class="px-3 py-2 border-b border-gray-800 flex items-center gap-2 shrink-0">
                <div class="w-2 h-2 rounded-full shrink-0" :class="STATE_DOT[connState]"/>
                <span class="font-medium" :class="STATE_COLORS[connState]">
                    {{ connState === 'connected' ? `Connected (${wsConfig.driver})` : connState }}
                </span>
                <span v-if="socketId" class="text-gray-600 truncate flex-1">ID: {{ socketId }}</span>
                <span v-else class="flex-1"/>
                <span v-if="connError" class="text-red-400 truncate max-w-[140px]" :title="connError">{{ connError }}</span>

                <button
                    v-if="connState !== 'connected' && connState !== 'connecting'"
                    class="px-2 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors"
                    @click="connect"
                >Connect</button>
                <button
                    v-else-if="connState === 'connected'"
                    class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded transition-colors"
                    @click="disconnect"
                >Disconnect</button>
            </div>

            <!-- WS URL display -->
            <div class="px-3 py-1.5 border-b border-gray-800 flex items-center gap-1 shrink-0 text-gray-600">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <span class="font-mono truncate">
                    {{ wsConfig.scheme === 'https' ? 'wss' : 'ws' }}://{{ wsConfig.host }}:{{ wsConfig.port }}{{ wsConfig.path }}/{{ wsConfig.app_key }}
                </span>
            </div>

            <!-- Subscribe form -->
            <div class="px-3 py-2 border-b border-gray-800 flex gap-2 shrink-0">
                <input
                    v-model="channelInput"
                    type="text"
                    placeholder="channel-name (e.g. orders, private-user.1)"
                    class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                    :disabled="connState !== 'connected'"
                    @keydown.enter="handleSubscribeInput"
                />
                <button
                    class="px-2 py-1 text-xs bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white rounded transition-colors shrink-0"
                    :disabled="!channelInput.trim() || connState !== 'connected'"
                    @click="handleSubscribeInput"
                >Subscribe</button>
            </div>

            <!-- Subscribed channels -->
            <div v-if="subscribed.size > 0" class="px-3 py-1.5 border-b border-gray-800 flex items-center gap-1 flex-wrap shrink-0">
                <span class="text-gray-600 mr-1">Channels:</span>
                <span
                    v-for="ch in subscribed"
                    :key="ch"
                    class="inline-flex items-center gap-1 bg-indigo-500/15 text-indigo-300 rounded px-1.5 py-0.5"
                >
                    {{ ch }}
                    <button class="text-indigo-500 hover:text-red-400 leading-none" @click="sendUnsubscribe(ch)">×</button>
                </span>
            </div>

            <!-- Event log toolbar -->
            <div class="px-3 py-2 border-b border-gray-800 flex items-center gap-2 shrink-0">
                <input
                    v-model="filterText"
                    type="text"
                    placeholder="Filter events…"
                    class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                />
                <button
                    class="shrink-0 text-gray-500 hover:text-red-400 transition-colors"
                    title="Clear event log"
                    @click="clearLog"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>

            <!-- Event log -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-if="filtered.length === 0"
                    class="flex items-center justify-center h-16 text-gray-700 italic"
                >
                    {{ connState === 'connected' ? 'Waiting for events…' : 'Connect to start receiving events.' }}
                </div>

                <div
                    v-for="event in filtered"
                    :key="event.id"
                    class="px-3 py-1.5 border-b border-gray-800/50 hover:bg-gray-800/30 group"
                >
                    <div class="flex items-start gap-2">
                        <span class="shrink-0 w-3 font-mono" :class="dirColor(event.direction)">{{ dirIcon(event.direction) }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span v-if="event.channel" class="text-indigo-400 truncate">{{ event.channel }}</span>
                                <span class="text-gray-300 font-medium truncate">{{ event.event }}</span>
                                <span class="ml-auto text-gray-700 tabular-nums shrink-0">{{ formatTime(event.ts) }}</span>
                            </div>
                            <pre
                                v-if="event.data !== null && event.data !== undefined && event.data !== '{}'"
                                class="mt-1 text-gray-500 whitespace-pre-wrap break-all"
                                style="font-size:10px"
                            >{{ formatData(event.data) }}</pre>
                        </div>
                    </div>
                </div>
            </div>

        </template>

        <!-- Loading -->
        <div v-else class="flex-1 flex items-center justify-center text-gray-700 italic">Loading…</div>
    </div>
</template>
