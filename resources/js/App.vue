<script setup lang="ts">
import { reactive, ref } from 'vue'
import RouteSidebar from './components/RouteSidebar.vue'
import CollectionsSidebar from './components/CollectionsSidebar.vue'
import RequestBuilder from './components/RequestBuilder.vue'
import ResponseViewer from './components/ResponseViewer.vue'
import { useProxy } from './composables/useProxy'
import type { ActiveRequest, LarafiedConfig, RequestData } from './types'

const props = defineProps<{ config: LarafiedConfig }>()

type SidebarTab = 'routes' | 'collections'
const activeTab = ref<SidebarTab>('routes')

const request = reactive<ActiveRequest>({
    method:  'GET',
    url:     '',
    headers: [],
    body:    '',
})

const { response, sending, error: proxyError, send } = useProxy()

function loadRequest(data: RequestData): void {
    request.method  = data.method
    request.url     = data.url ?? ''
    request.headers = Object.entries(data.headers ?? {}).map(([key, value]) => ({
        key,
        value,
        enabled: true,
    }))
    request.body = typeof data.body === 'string' ? data.body : ''
}

async function handleSend(): Promise<void> {
    const headers: Record<string, string> = {}
    for (const h of request.headers) {
        if (h.enabled && h.key.trim()) {
            headers[h.key.trim()] = h.value
        }
    }

    await send({
        method:  request.method,
        url:     request.url,
        headers: Object.keys(headers).length > 0 ? headers : undefined,
        body:    request.body.trim() || undefined,
    })
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-950 text-gray-100">
        <!-- Left sidebar -->
        <aside class="w-72 flex flex-col border-r border-gray-800 shrink-0">
            <!-- Branding -->
            <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <span class="font-semibold text-sm text-white">{{ props.config.title }}</span>
                <span class="text-xs text-gray-600">v{{ props.config.version }}</span>
            </div>

            <!-- Tab switcher -->
            <div class="flex border-b border-gray-800">
                <button
                    v-for="tab in (['routes', 'collections'] as SidebarTab[])"
                    :key="tab"
                    class="flex-1 py-2 text-xs font-medium capitalize transition-colors border-b-2 -mb-px"
                    :class="activeTab === tab
                        ? 'border-indigo-500 text-white'
                        : 'border-transparent text-gray-500 hover:text-gray-300'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                </button>
            </div>

            <!-- Sidebar content -->
            <div class="flex-1 overflow-hidden">
                <RouteSidebar
                    v-if="activeTab === 'routes'"
                    @select="loadRequest"
                />
                <CollectionsSidebar
                    v-else
                    :current-request="request"
                    @select="loadRequest"
                />
            </div>
        </aside>

        <!-- Main panel -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <RequestBuilder
                :request="request"
                :sending="sending"
                @send="handleSend"
            />
            <ResponseViewer
                :response="response"
                :error="proxyError"
                :sending="sending"
            />
        </div>
    </div>
</template>
