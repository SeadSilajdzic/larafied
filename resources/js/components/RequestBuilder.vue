<script setup lang="ts">
import { ref } from 'vue'
import type { ActiveRequest } from '../types'

const props = defineProps<{
    request: ActiveRequest
    sending: boolean
}>()

const emit = defineEmits<{
    send: []
}>()

type BodyType = 'json' | 'raw'

const activeTab = ref<'headers' | 'body'>('headers')
const bodyType  = ref<BodyType>('json')

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']

const JSON_PLACEHOLDER = '{\n  "key": "value"\n}'

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

function addHeader(): void {
    props.request.headers.push({ key: '', value: '', enabled: true })
}

function removeHeader(index: number): void {
    props.request.headers.splice(index, 1)
}

function handleSend(): void {
    if (!props.request.url.trim()) return
    emit('send')
}

function handleKeydown(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        handleSend()
    }
}
</script>

<template>
    <div class="flex flex-col border-b border-gray-800 shrink-0">
        <!-- URL bar -->
        <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-800">
            <select
                v-model="request.method"
                class="bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs font-mono font-semibold focus:outline-none focus:border-indigo-500 shrink-0 cursor-pointer"
                :class="METHOD_COLORS[request.method] ?? 'text-gray-400'"
            >
                <option v-for="m in METHODS" :key="m" :value="m">{{ m }}</option>
            </select>

            <input
                v-model="request.url"
                type="text"
                placeholder="https://example.com/api/endpoint"
                class="flex-1 bg-gray-900 border border-gray-700 rounded px-3 py-1.5 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                @keydown="handleKeydown"
            />

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

        <!-- Tabs -->
        <div class="flex border-b border-gray-800 px-3">
            <button
                v-for="tab in (['headers', 'body'] as const)"
                :key="tab"
                class="py-2 px-1 mr-4 text-xs font-medium capitalize transition-colors border-b-2 -mb-px"
                :class="activeTab === tab
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
                @click="activeTab = tab"
            >
                {{ tab }}
                <span
                    v-if="tab === 'headers' && request.headers.filter(h => h.enabled && h.key).length"
                    class="ml-1 text-indigo-400"
                >
                    ({{ request.headers.filter(h => h.enabled && h.key).length }})
                </span>
            </button>
        </div>

        <!-- Headers tab -->
        <div v-if="activeTab === 'headers'" class="px-3 py-2 max-h-40 overflow-y-auto">
            <div
                v-for="(header, i) in request.headers"
                :key="i"
                class="flex items-center gap-2 mb-1.5"
            >
                <input
                    v-model="header.enabled"
                    type="checkbox"
                    class="accent-indigo-500 shrink-0"
                />
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
                <button
                    class="text-gray-600 hover:text-red-400 transition-colors shrink-0 text-sm leading-none"
                    @click="removeHeader(i)"
                >
                    ×
                </button>
            </div>
            <button
                class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors mt-1"
                @click="addHeader"
            >
                + Add header
            </button>
        </div>

        <!-- Body tab -->
        <div v-if="activeTab === 'body'" class="flex flex-col px-3 py-2">
            <div class="flex items-center gap-3 mb-2">
                <label
                    v-for="type in (['json', 'raw'] as BodyType[])"
                    :key="type"
                    class="flex items-center gap-1 text-xs text-gray-400 cursor-pointer"
                >
                    <input
                        v-model="bodyType"
                        type="radio"
                        :value="type"
                        class="accent-indigo-500"
                    />
                    {{ type === 'json' ? 'JSON' : 'Raw' }}
                </label>
            </div>
            <textarea
                v-model="request.body"
                :placeholder="bodyType === 'json' ? JSON_PLACEHOLDER : 'Request body…'"
                rows="6"
                class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 resize-none"
                @keydown="handleKeydown"
            />
        </div>
    </div>
</template>
