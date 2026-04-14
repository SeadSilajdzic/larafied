<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoutes } from '../composables/useRoutes'
import { useNotes } from '../composables/useNotes'
import ContextMenu from './ContextMenu.vue'
import type { ContextMenuItem } from './ContextMenu.vue'
import type { Route, RequestData } from '../types'

const emit = defineEmits<{
    select: [request: RequestData]
}>()

const { groups, loading, error, fetchRoutes } = useRoutes()
const { notes, fetchNotes, noteForRoute, saveNote, deleteNote } = useNotes()

const search          = ref('')
const collapsedGroups = ref<Set<string>>(new Set())

// Context menu state
const menuVisible  = ref(false)
const menuX        = ref(0)
const menuY        = ref(0)
const menuItems    = ref<ContextMenuItem[]>([])

// Note editor state
const noteRoute    = ref<Route | null>(null)
const noteText     = ref('')
const noteSaving   = ref(false)

function toggleGroup(name: string): void {
    if (collapsedGroups.value.has(name)) {
        collapsedGroups.value.delete(name)
    } else {
        collapsedGroups.value.add(name)
    }
    collapsedGroups.value = new Set(collapsedGroups.value)
}

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return groups.value

    return groups.value
        .map((g) => ({
            ...g,
            routes: g.routes.filter(
                (r) =>
                    r.uri.toLowerCase().includes(q) ||
                    (r.name ?? '').toLowerCase().includes(q) ||
                    r.action.toLowerCase().includes(q),
            ),
        }))
        .filter((g) => g.routes.length > 0)
})

function selectRoute(route: Route): void {
    emit('select', {
        method: route.methods[0] ?? 'GET',
        url: `/${route.uri}`,
    })
}

function openContextMenu(e: MouseEvent, route: Route): void {
    e.preventDefault()
    const method  = route.methods[0] ?? 'GET'
    const hasNote = !!noteForRoute(method, route.uri)

    menuX.value = e.clientX
    menuY.value = e.clientY
    menuItems.value = [
        {
            label: 'Load request',
            icon:  '→',
            action: () => selectRoute(route),
        },
        {
            label: 'Copy URL',
            icon:  '⎘',
            action: () => navigator.clipboard?.writeText(`${window.location.origin}/${route.uri}`),
        },
        {
            label: 'Copy as cURL',
            icon:  '$',
            action: () => navigator.clipboard?.writeText(
                `curl -X ${method} "${window.location.origin}/${route.uri}"`,
            ),
        },
        {
            label:  hasNote ? 'Edit note' : 'Add note',
            icon:   '✎',
            action: () => openNoteEditor(route),
        },
        ...(hasNote ? [{
            label:  'Remove note',
            icon:   '✕',
            danger: true,
            action: () => deleteNote(method, route.uri),
        }] : []),
    ]
    menuVisible.value = true
}

function openNoteEditor(route: Route): void {
    noteRoute.value = route
    const existing  = noteForRoute(route.methods[0] ?? 'GET', route.uri)
    noteText.value  = existing?.note ?? ''
}

async function handleNoteSave(): Promise<void> {
    if (!noteRoute.value) return
    noteSaving.value = true
    try {
        await saveNote(noteRoute.value.methods[0] ?? 'GET', noteRoute.value.uri, noteText.value)
        noteRoute.value = null
    } finally {
        noteSaving.value = false
    }
}

const METHOD_BADGE: Record<string, string> = {
    GET:     'bg-emerald-500/10 text-emerald-400 border-emerald-500/25',
    POST:    'bg-blue-500/10 text-blue-400 border-blue-500/25',
    PUT:     'bg-amber-500/10 text-amber-400 border-amber-500/25',
    PATCH:   'bg-orange-500/10 text-orange-400 border-orange-500/25',
    DELETE:  'bg-red-500/10 text-red-400 border-red-500/25',
    HEAD:    'bg-purple-500/10 text-purple-400 border-purple-500/25',
    OPTIONS: 'bg-gray-500/10 text-gray-400 border-gray-500/25',
}

function methodBadge(method: string): string {
    return METHOD_BADGE[method.toUpperCase()] ?? 'bg-gray-500/10 text-gray-400 border-gray-500/25'
}

onMounted(async () => {
    await fetchRoutes()
    await fetchNotes()
})
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Search -->
        <div class="px-3 py-2 border-b border-gray-800">
            <input
                v-model="search"
                type="text"
                placeholder="Search routes…"
                class="w-full bg-gray-900 text-gray-200 text-xs rounded px-2 py-1.5 placeholder-gray-600 border border-gray-700 focus:outline-none focus:border-indigo-500"
            />
        </div>

        <!-- States -->
        <div v-if="loading" class="flex-1 flex items-center justify-center text-gray-600 text-xs">
            Loading routes…
        </div>

        <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center gap-2 px-4">
            <p class="text-red-400 text-xs text-center">{{ error }}</p>
            <button
                class="text-xs text-indigo-400 hover:text-indigo-300"
                @click="fetchRoutes"
            >
                Retry
            </button>
        </div>

        <div
            v-else-if="filtered.length === 0"
            class="flex-1 flex items-center justify-center text-gray-600 text-xs"
        >
            {{ search ? 'No routes match your search.' : 'No routes found.' }}
        </div>

        <!-- Route groups -->
        <div v-else class="flex-1 overflow-y-auto">
            <div v-for="group in filtered" :key="group.group">
                <!-- Group header (clickable to collapse) -->
                <button
                    class="w-full px-3 py-1.5 flex items-center justify-between bg-gray-900/70 sticky top-0 border-b border-gray-800/60 hover:bg-gray-800/60 transition-colors"
                    @click="toggleGroup(group.group)"
                >
                    <div class="flex items-center gap-1.5">
                        <svg
                            class="w-3 h-3 text-gray-600 shrink-0 transition-transform"
                            :class="collapsedGroups.has(group.group) ? '-rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            {{ group.group }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-600 tabular-nums">
                        {{ group.routes.length }}
                    </span>
                </button>

                <!-- Route rows (hidden when collapsed) -->
                <template v-if="!collapsedGroups.has(group.group)">
                    <button
                        v-for="route in group.routes"
                        :key="route.uri + route.methods.join()"
                        class="w-full text-left px-3 py-2 flex items-start gap-2.5 hover:bg-gray-800/50 transition-colors border-b border-gray-800/30"
                        @click="selectRoute(route)"
                        @contextmenu.prevent="openContextMenu($event, route)"
                    >
                        <!-- Method badge -->
                        <span
                            class="inline-flex items-center justify-center text-xs font-mono font-bold rounded border px-1.5 py-0.5 min-w-[52px] shrink-0 leading-tight mt-px"
                            :class="methodBadge(route.methods[0] ?? 'GET')"
                        >
                            {{ route.methods[0] }}
                        </span>

                        <!-- URI + name -->
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="text-xs text-gray-200 truncate font-mono leading-tight">
                                /{{ route.uri }}
                            </span>
                            <span v-if="route.name" class="text-xs text-gray-600 truncate mt-0.5 leading-tight">
                                {{ route.name }}
                            </span>
                        </div>

                        <!-- Note indicator -->
                        <span
                            v-if="noteForRoute(route.methods[0] ?? 'GET', route.uri)"
                            class="text-indigo-400 shrink-0 mt-px"
                            title="Has note"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                        </span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Context menu -->
    <ContextMenu
        v-if="menuVisible"
        :x="menuX"
        :y="menuY"
        :items="menuItems"
        @close="menuVisible = false"
    />

    <!-- Note editor modal -->
    <Teleport v-if="noteRoute" to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
            @mousedown.self="noteRoute = null"
        >
            <div class="bg-gray-900 border border-gray-700 rounded-lg shadow-2xl w-96 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-white">Note</span>
                        <span class="ml-2 text-xs text-gray-500 font-mono">/{{ noteRoute.uri }}</span>
                    </div>
                    <button
                        class="text-gray-600 hover:text-gray-400 text-lg leading-none"
                        @click="noteRoute = null"
                    >
                        ×
                    </button>
                </div>
                <div class="p-4">
                    <textarea
                        v-model="noteText"
                        placeholder="Write a note about this endpoint…"
                        rows="5"
                        class="w-full bg-gray-950 border border-gray-700 rounded px-3 py-2 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 resize-none"
                        autofocus
                        @keydown.ctrl.enter="handleNoteSave"
                        @keydown.meta.enter="handleNoteSave"
                        @keydown.esc="noteRoute = null"
                    />
                    <p class="text-xs text-gray-700 mt-1">Ctrl+Enter to save</p>
                </div>
                <div class="px-4 pb-4 flex justify-end gap-2">
                    <button
                        class="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200 transition-colors"
                        @click="noteRoute = null"
                    >
                        Cancel
                    </button>
                    <button
                        class="px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors disabled:opacity-50"
                        :disabled="noteSaving || !noteText.trim()"
                        @click="handleNoteSave"
                    >
                        {{ noteSaving ? 'Saving…' : 'Save note' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
