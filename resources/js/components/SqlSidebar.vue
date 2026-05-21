<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import type { SqlTable } from '../types'

const emit = defineEmits<{
    insert: [text: string]
}>()

const tables   = ref<SqlTable[]>([])
const database = ref<string>('')
const loading  = ref(false)
const error    = ref<string | null>(null)
const search   = ref('')
const expanded = ref<string[]>([])

const api = useApi()

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return tables.value

    return tables.value
        .map(t => ({
            ...t,
            columns: t.columns.filter(c => c.name.toLowerCase().includes(q)),
        }))
        .filter(t => t.name.toLowerCase().includes(q) || t.columns.length > 0)
})

function toggle(name: string): void {
    const idx = expanded.value.indexOf(name)
    if (idx >= 0) {
        expanded.value.splice(idx, 1)
    } else {
        expanded.value.push(name)
    }
}

function isExpanded(name: string): boolean {
    return expanded.value.includes(name)
}

async function fetchTables(): Promise<void> {
    loading.value = true
    error.value   = null

    try {
        const data = await api.get<{ tables: SqlTable[]; database: string } | { error: string }>('/sql/tables')

        if ('error' in data) {
            error.value = data.error
        } else {
            tables.value   = data.tables
            database.value = data.database
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to load tables.'
    } finally {
        loading.value = false
    }
}

onMounted(fetchTables)
</script>

<template>
    <div class="flex flex-col w-52 shrink-0 border-r border-gray-800 overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between px-2 py-2 border-b border-gray-800 shrink-0">
            <div class="flex flex-col min-w-0">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide leading-none">Tables</span>
                <span v-if="database" class="text-xs text-gray-600 font-mono truncate mt-0.5">{{ database }}</span>
            </div>
            <button
                class="p-0.5 rounded text-gray-600 hover:text-gray-300 transition-colors shrink-0"
                title="Refresh"
                @click="fetchTables"
            >
                <svg class="w-3 h-3" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>

        <!-- Search -->
        <div class="px-2 py-1.5 border-b border-gray-800 shrink-0">
            <input
                v-model="search"
                type="text"
                placeholder="Search tables…"
                class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
            />
        </div>

        <!-- Table list — scrollable -->
        <div class="flex-1 overflow-y-auto pb-4">

            <div v-if="loading" class="px-3 py-4 text-xs text-gray-600 text-center">
                Loading…
            </div>

            <div v-else-if="error" class="px-3 py-4 text-xs text-red-400 text-center">
                {{ error }}
            </div>

            <div v-else-if="filtered.length === 0" class="px-3 py-4 text-xs text-gray-600 text-center">
                No tables found.
            </div>

            <template v-else>
                <div v-for="table in filtered" :key="table.name">

                    <!-- Table row -->
                    <div class="flex items-center group">
                        <button
                            class="flex items-center gap-1.5 flex-1 min-w-0 px-2 py-1.5 text-left hover:bg-gray-800/70 transition-colors"
                            @click="toggle(table.name)"
                        >
                            <svg
                                class="w-2.5 h-2.5 text-gray-600 shrink-0 transition-transform"
                                :class="isExpanded(table.name) ? 'rotate-0' : '-rotate-90'"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <svg class="w-3 h-3 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/>
                            </svg>
                            <span class="text-xs font-mono text-gray-300 truncate">{{ table.name }}</span>
                        </button>
                        <button
                            class="px-1.5 py-1.5 text-gray-700 hover:text-indigo-400 transition-colors opacity-0 group-hover:opacity-100 shrink-0"
                            title="Insert table name"
                            @click.stop="emit('insert', table.name)"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Columns -->
                    <template v-if="isExpanded(table.name)">
                        <div
                            v-for="col in table.columns"
                            :key="col.name"
                            class="flex items-center group/col pl-7 pr-1 py-0.5 hover:bg-gray-800/40 transition-colors cursor-default"
                        >
                            <span class="flex-1 min-w-0 text-xs font-mono text-gray-500 truncate">{{ col.name }}</span>
                            <span class="text-xs text-gray-700 shrink-0 mr-1 font-mono">{{ col.type }}</span>
                            <button
                                class="p-0.5 text-gray-700 hover:text-indigo-400 transition-colors opacity-0 group-hover/col:opacity-100 shrink-0"
                                title="Insert column name"
                                @click.stop="emit('insert', col.name)"
                            >
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </template>

                </div>
            </template>

        </div>
    </div>
</template>
