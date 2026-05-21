<script setup lang="ts">
import { ref, nextTick } from 'vue'
import type { SqlTab } from '../types'

const props = defineProps<{
    tabs:     SqlTab[]
    activeId: string
}>()

const emit = defineEmits<{
    switch: [id: string]
    add:    []
    close:  [id: string]
    rename: [id: string, name: string]
}>()

const renamingId   = ref<string | null>(null)
const renameBuffer = ref('')
const renameInput  = ref<HTMLInputElement | null>(null)

async function startRename(tab: SqlTab): Promise<void> {
    renamingId.value   = tab.id
    renameBuffer.value = tab.name
    await nextTick()
    renameInput.value?.select()
}

function commitRename(id: string): void {
    const name = renameBuffer.value.trim()
    if (name) emit('rename', id, name)
    renamingId.value = null
}

function handleRenameKeydown(e: KeyboardEvent, id: string): void {
    if (e.key === 'Enter')  { e.preventDefault(); commitRename(id) }
    if (e.key === 'Escape') renamingId.value = null
}
</script>

<template>
    <div class="flex items-stretch border-b border-gray-800 bg-gray-950 shrink-0 overflow-x-auto">

        <div
            v-for="tab in tabs"
            :key="tab.id"
            class="flex items-stretch shrink-0 border-r border-gray-800 group/tab relative"
            :class="tab.id === activeId ? 'bg-gray-900' : 'bg-gray-950 hover:bg-gray-900/50'"
        >
            <!-- Active indicator -->
            <div
                v-if="tab.id === activeId"
                class="absolute bottom-0 inset-x-0 h-px bg-indigo-500"
            />

            <!-- Tab label / rename input -->
            <button
                v-if="renamingId !== tab.id"
                class="px-3 py-1.5 text-xs font-mono transition-colors truncate max-w-36"
                :class="tab.id === activeId ? 'text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                @click="emit('switch', tab.id)"
                @dblclick.prevent="startRename(tab)"
            >{{ tab.name }}</button>

            <input
                v-else
                ref="renameInput"
                v-model="renameBuffer"
                class="px-3 py-1.5 text-xs font-mono bg-transparent text-gray-200 focus:outline-none w-28"
                @blur="commitRename(tab.id)"
                @keydown="handleRenameKeydown($event, tab.id)"
            />

            <!-- Close button -->
            <button
                v-if="tabs.length > 1"
                class="pr-2 pl-0.5 text-gray-600 hover:text-gray-300 transition-colors opacity-0 group-hover/tab:opacity-100 shrink-0"
                title="Close tab"
                @click.stop="emit('close', tab.id)"
            >
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div v-else class="w-3 shrink-0" />
        </div>

        <!-- Add tab -->
        <button
            class="px-2.5 py-1.5 text-gray-600 hover:text-gray-300 transition-colors shrink-0"
            title="New query tab"
            @click="emit('add')"
        >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>
</template>
