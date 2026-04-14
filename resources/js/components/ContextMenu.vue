<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

export interface ContextMenuItem {
    label: string
    icon?: string
    danger?: boolean
    action: () => void
}

const props = defineProps<{
    x: number
    y: number
    items: ContextMenuItem[]
}>()

const emit = defineEmits<{ close: [] }>()

function handleOutsideClick(): void {
    emit('close')
}

function handleItem(item: ContextMenuItem): void {
    item.action()
    emit('close')
}

onMounted(() => {
    // Use setTimeout so the current right-click event doesn't immediately close the menu
    setTimeout(() => document.addEventListener('mousedown', handleOutsideClick), 0)
})

onUnmounted(() => {
    document.removeEventListener('mousedown', handleOutsideClick)
})
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed z-50 min-w-[160px] bg-gray-800 border border-gray-700 rounded shadow-xl py-1 text-xs"
            :style="{ left: x + 'px', top: y + 'px' }"
            @mousedown.stop
        >
            <button
                v-for="item in items"
                :key="item.label"
                class="w-full text-left px-3 py-1.5 flex items-center gap-2 transition-colors"
                :class="item.danger
                    ? 'text-red-400 hover:bg-red-500/10'
                    : 'text-gray-300 hover:bg-gray-700'"
                @click="handleItem(item)"
            >
                <span v-if="item.icon" class="w-3.5 text-center shrink-0">{{ item.icon }}</span>
                {{ item.label }}
            </button>
        </div>
    </Teleport>
</template>
