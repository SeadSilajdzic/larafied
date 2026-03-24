<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoutes } from '../composables/useRoutes'
import type { Route, RequestData } from '../types'

const emit = defineEmits<{
    select: [request: RequestData]
}>()

const { groups, loading, error, fetchRoutes } = useRoutes()

const search = ref('')

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

const METHOD_COLORS: Record<string, string> = {
    GET:     'text-emerald-400',
    POST:    'text-blue-400',
    PUT:     'text-amber-400',
    PATCH:   'text-orange-400',
    DELETE:  'text-red-400',
    HEAD:    'text-purple-400',
    OPTIONS: 'text-gray-400',
}

function methodColor(method: string): string {
    return METHOD_COLORS[method.toUpperCase()] ?? 'text-gray-400'
}

onMounted(fetchRoutes)
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
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-900/50 sticky top-0">
                    {{ group.group }}
                </div>
                <button
                    v-for="route in group.routes"
                    :key="route.uri + route.methods.join()"
                    class="w-full text-left px-3 py-2 flex items-center gap-2 hover:bg-gray-800/60 transition-colors group"
                    @click="selectRoute(route)"
                >
                    <span
                        class="text-xs font-mono font-semibold w-14 shrink-0"
                        :class="methodColor(route.methods[0] ?? 'GET')"
                    >
                        {{ route.methods[0] }}
                    </span>
                    <span class="text-xs text-gray-300 truncate font-mono">
                        /{{ route.uri }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
