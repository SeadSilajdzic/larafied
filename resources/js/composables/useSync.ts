import { ref } from 'vue'
import { useApi } from './useApi'

type SyncState = 'idle' | 'pushing' | 'pulling' | 'success' | 'error'

export function useSync() {
    const { post, get } = useApi()

    const state   = ref<SyncState>('idle')
    const message = ref<string | null>(null)

    async function push(): Promise<void> {
        state.value   = 'pushing'
        message.value = null
        try {
            const result = await post<{ ok?: boolean; error?: string }>('sync/push', {})
            if (result.ok) {
                state.value   = 'success'
                message.value = 'Workspace pushed to cloud.'
            } else {
                state.value   = 'error'
                message.value = result.error ?? 'Push failed.'
            }
        } catch {
            state.value   = 'error'
            message.value = 'Push failed — check your connection.'
        }
        setTimeout(() => { state.value = 'idle'; message.value = null }, 3000)
    }

    async function pull(): Promise<void> {
        state.value   = 'pulling'
        message.value = null
        try {
            const result = await post<{ ok?: boolean; error?: string; updated_at?: string }>('sync/pull', {})
            if (result.ok) {
                state.value   = 'success'
                message.value = 'Workspace pulled from cloud.'
            } else {
                state.value   = 'error'
                message.value = result.error ?? 'Pull failed.'
            }
        } catch {
            state.value   = 'error'
            message.value = 'Pull failed — check your connection.'
        }
        setTimeout(() => { state.value = 'idle'; message.value = null }, 3000)
    }

    async function checkStatus(): Promise<boolean> {
        try {
            const result = await get<{ enabled: boolean }>('sync/status')
            return result.enabled === true
        } catch {
            return false
        }
    }

    return { state, message, push, pull, checkStatus }
}
