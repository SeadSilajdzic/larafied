<script setup lang="ts">
import { ref } from 'vue'

const props  = defineProps<{ loading: boolean; error: string | null }>()
const emit   = defineEmits<{ activate: [key: string]; close: [] }>()
const key    = ref('')

function submit(): void {
    if (key.value.trim()) {
        emit('activate', key.value.trim())
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-gray-900 border border-gray-700 rounded-lg shadow-xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-white">Activate License</h2>
                <button
                    class="text-gray-500 hover:text-gray-300 text-xs"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>

            <p class="text-xs text-gray-400 mb-4">
                Enter your Larafied license key to unlock Pro or Team features.
            </p>

            <form @submit.prevent="submit" class="space-y-3">
                <input
                    v-model="key"
                    type="text"
                    placeholder="AW-XXXX-XXXX-XXXX-XXXX"
                    spellcheck="false"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                />

                <div
                    v-if="props.error"
                    class="text-xs text-red-400 bg-red-900/30 border border-red-800 rounded px-3 py-2"
                >
                    {{ props.error }}
                </div>

                <button
                    type="submit"
                    :disabled="props.loading || !key.trim()"
                    class="w-full py-2 text-sm font-medium rounded bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ props.loading ? 'Activating…' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>
</template>
