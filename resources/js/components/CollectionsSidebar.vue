<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCollections } from '../composables/useCollections'
import type { ActiveRequest, RequestData } from '../types'

const props = defineProps<{
    currentRequest: ActiveRequest
}>()

const emit = defineEmits<{
    select: [request: RequestData]
}>()

const {
    collections,
    loading,
    limitReached,
    fetchCollections,
    createCollection,
    deleteCollection,
    saveRequest,
    deleteRequest,
} = useCollections()

const expanded       = ref<Set<string>>(new Set())
const newName        = ref('')
const showCreate     = ref(false)
const saving         = ref<string | null>(null)
const saveName       = ref('')
const showSaveIn     = ref<string | null>(null)

function toggleExpand(id: string): void {
    if (expanded.value.has(id)) {
        expanded.value.delete(id)
    } else {
        expanded.value.add(id)
    }
}

async function handleCreate(): Promise<void> {
    const name = newName.value.trim()
    if (!name) return

    await createCollection(name)
    newName.value  = ''
    showCreate.value = false
}

async function handleDelete(id: string): Promise<void> {
    await deleteCollection(id)
    expanded.value.delete(id)
}

async function handleSaveRequest(collectionId: string): Promise<void> {
    const name = saveName.value.trim()
    if (!name) return

    saving.value = collectionId

    const headers: Record<string, string> = {}
    for (const h of props.currentRequest.headers) {
        if (h.enabled && h.key) headers[h.key] = h.value
    }

    await saveRequest(collectionId, {
        name,
        data: {
            method:  props.currentRequest.method,
            url:     props.currentRequest.url,
            headers: Object.keys(headers).length > 0 ? headers : undefined,
            body:    props.currentRequest.body || undefined,
        },
    })

    saving.value     = null
    saveName.value   = ''
    showSaveIn.value = null
    expanded.value.add(collectionId)
}

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

onMounted(fetchCollections)
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Header actions -->
        <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
            <span class="text-xs text-gray-500">
                {{ collections.length }} / 5
                <span v-if="limitReached" class="text-amber-400 ml-1">Limit reached</span>
            </span>
            <button
                class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="limitReached"
                @click="showCreate = !showCreate"
            >
                + New
            </button>
        </div>

        <!-- Create form -->
        <div v-if="showCreate" class="px-3 py-2 border-b border-gray-800 flex gap-2">
            <input
                v-model="newName"
                type="text"
                placeholder="Collection name"
                class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                @keydown.enter="handleCreate"
                @keydown.esc="showCreate = false"
            />
            <button
                class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                :disabled="!newName.trim()"
                @click="handleCreate"
            >
                Save
            </button>
        </div>

        <!-- States -->
        <div v-if="loading" class="flex-1 flex items-center justify-center text-gray-600 text-xs">
            Loading…
        </div>

        <div
            v-else-if="collections.length === 0"
            class="flex-1 flex items-center justify-center text-gray-700 text-xs text-center px-4"
        >
            No collections yet.<br />Create one to save requests.
        </div>

        <!-- Collections list -->
        <div v-else class="flex-1 overflow-y-auto">
            <div v-for="col in collections" :key="col.id">
                <!-- Collection header -->
                <div
                    class="flex items-center gap-1 px-3 py-2 hover:bg-gray-800/40 cursor-pointer group"
                    @click="toggleExpand(col.id)"
                >
                    <span class="text-gray-600 text-xs w-3 shrink-0">
                        {{ expanded.has(col.id) ? '▾' : '▸' }}
                    </span>
                    <span class="flex-1 text-xs text-gray-300 font-medium truncate">
                        {{ col.name }}
                    </span>
                    <span class="text-xs text-gray-600 mr-1">
                        {{ col.requests.length }}
                    </span>
                    <!-- Save current request here -->
                    <button
                        class="text-gray-600 hover:text-indigo-400 transition-colors text-xs shrink-0 opacity-0 group-hover:opacity-100"
                        title="Save current request here"
                        @click.stop="showSaveIn = showSaveIn === col.id ? null : col.id; saveName = ''"
                    >
                        ↓
                    </button>
                    <button
                        class="text-gray-600 hover:text-red-400 transition-colors text-sm leading-none shrink-0 opacity-0 group-hover:opacity-100"
                        title="Delete collection"
                        @click.stop="handleDelete(col.id)"
                    >
                        ×
                    </button>
                </div>

                <!-- Save-here inline form -->
                <div
                    v-if="showSaveIn === col.id"
                    class="flex gap-2 px-3 pb-2"
                    @click.stop
                >
                    <input
                        v-model="saveName"
                        type="text"
                        placeholder="Request name"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                        @keydown.enter="handleSaveRequest(col.id)"
                        @keydown.esc="showSaveIn = null"
                    />
                    <button
                        class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                        :disabled="!saveName.trim() || saving === col.id"
                        @click="handleSaveRequest(col.id)"
                    >
                        {{ saving === col.id ? '…' : 'Save' }}
                    </button>
                </div>

                <!-- Saved requests -->
                <div v-if="expanded.has(col.id)">
                    <div
                        v-if="col.requests.length === 0"
                        class="px-6 py-1.5 text-xs text-gray-700 italic"
                    >
                        Empty
                    </div>
                    <div
                        v-for="req in col.requests"
                        :key="req.id"
                        class="flex items-center gap-2 px-5 py-1.5 hover:bg-gray-800/50 cursor-pointer group/req"
                        @click="emit('select', req.data)"
                    >
                        <span
                            class="text-xs font-mono font-semibold w-12 shrink-0"
                            :class="METHOD_COLORS[req.data.method?.toUpperCase()] ?? 'text-gray-400'"
                        >
                            {{ req.data.method }}
                        </span>
                        <span class="flex-1 text-xs text-gray-400 truncate">
                            {{ req.name }}
                        </span>
                        <button
                            class="text-gray-700 hover:text-red-400 transition-colors text-sm leading-none shrink-0 opacity-0 group-hover/req:opacity-100"
                            title="Delete request"
                            @click.stop="deleteRequest(req.id)"
                        >
                            ×
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
