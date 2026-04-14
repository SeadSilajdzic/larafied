import { ref } from 'vue'
import { useApi } from './useApi'
import type { RouteNote } from '../types'

export function useNotes() {
    const notes   = ref<RouteNote[]>([])
    const loading = ref(false)

    async function fetchNotes(): Promise<void> {
        loading.value = true
        try {
            const api  = useApi()
            notes.value = await api.get<RouteNote[]>('/notes')
        } finally {
            loading.value = false
        }
    }

    function noteForRoute(method: string, uri: string): RouteNote | undefined {
        const normalUri = uri.replace(/^\//, '')
        return notes.value.find(
            (n) => n.method === method.toUpperCase() && n.uri === normalUri,
        )
    }

    async function saveNote(method: string, uri: string, note: string): Promise<void> {
        const api    = useApi()
        const saved  = await api.put<RouteNote>('/notes', {
            method: method.toUpperCase(),
            uri:    uri.replace(/^\//, ''),
            note,
        })
        const idx = notes.value.findIndex((n) => n.method === saved.method && n.uri === saved.uri)
        if (idx >= 0) {
            notes.value[idx] = saved
        } else {
            notes.value.push(saved)
        }
    }

    async function deleteNote(method: string, uri: string): Promise<void> {
        const api      = useApi()
        const normUri  = uri.replace(/^\//, '')
        await api.del(`/notes?method=${encodeURIComponent(method.toUpperCase())}&uri=${encodeURIComponent(normUri)}`)
        notes.value = notes.value.filter(
            (n) => !(n.method === method.toUpperCase() && n.uri === normUri),
        )
    }

    return { notes, loading, fetchNotes, noteForRoute, saveNote, deleteNote }
}
