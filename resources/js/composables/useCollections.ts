import { ref } from 'vue'
import { useApi } from './useApi'
import type { Collection, Folder, SavedRequest } from '../types'

interface CollectionsResponse {
    data: Collection[]
    meta: { count: number; limit: number; limit_reached: boolean }
}

export function useCollections() {
    const collections = ref<Collection[]>([])
    const loading     = ref(false)
    const limitReached = ref(false)

    async function fetchCollections(): Promise<void> {
        loading.value = true

        try {
            const api       = useApi()
            const res       = await api.get<CollectionsResponse>('/collections')
            collections.value  = res.data
            limitReached.value = res.meta.limit_reached
        } finally {
            loading.value = false
        }
    }

    async function createCollection(name: string, description?: string): Promise<Collection | { upgrade: true }> {
        const api    = useApi()
        const result = await api.post<Collection | { upgrade: boolean }>('/collections', { name, description })

        if ('upgrade' in result && result.upgrade) {
            limitReached.value = true
            return { upgrade: true }
        }

        await fetchCollections()
        return result as Collection
    }

    async function updateCollection(id: string, name: string, description?: string): Promise<Collection> {
        const api        = useApi()
        const collection = await api.put<Collection>(`/collections/${id}`, { name, description })
        await fetchCollections()
        return collection
    }

    async function deleteCollection(id: string): Promise<void> {
        const api = useApi()
        await api.del(`/collections/${id}`)
        collections.value = collections.value.filter((c) => c.id !== id)
    }

    async function saveRequest(
        collectionId: string,
        payload: { name: string; data: object },
    ): Promise<SavedRequest> {
        const api   = useApi()
        const saved = await api.post<SavedRequest>(`/collections/${collectionId}/requests`, payload)
        await fetchCollections()
        return saved
    }

    async function deleteRequest(requestId: string): Promise<void> {
        const api = useApi()
        await api.del(`/requests/${requestId}`)
        await fetchCollections()
    }

    async function duplicateRequest(requestId: string): Promise<SavedRequest> {
        const api  = useApi()
        const copy = await api.post<SavedRequest>(`/requests/${requestId}/duplicate`)
        await fetchCollections()
        return copy
    }

    async function renameRequest(
        collectionId: string,
        requestId: string,
        name: string,
        currentData: object,
    ): Promise<void> {
        const api = useApi()
        await api.put(`/collections/${collectionId}/requests/${requestId}`, { name, data: currentData })
        await fetchCollections()
    }

    async function bulkDeleteCollections(ids: string[]): Promise<void> {
        if (ids.length === 0) return
        const api = useApi()
        await api.del('/collections', { ids })
        collections.value = collections.value.filter((c) => !ids.includes(c.id))
    }

    async function createFolder(
        collectionId: string,
        name: string,
        description?: string,
    ): Promise<Folder> {
        const api    = useApi()
        const folder = await api.post<Folder>(`/collections/${collectionId}/folders`, { name, description })
        await fetchCollections()
        return folder
    }

    async function updateFolder(
        collectionId: string,
        folderId: string,
        name: string,
        description?: string,
    ): Promise<Folder> {
        const api    = useApi()
        const folder = await api.put<Folder>(`/collections/${collectionId}/folders/${folderId}`, { name, description })
        await fetchCollections()
        return folder
    }

    async function deleteFolder(collectionId: string, folderId: string): Promise<void> {
        const api = useApi()
        await api.del(`/collections/${collectionId}/folders/${folderId}`)
        await fetchCollections()
    }

    async function moveRequest(requestId: string, collectionId: string, folderId: string | null): Promise<void> {
        const api = useApi()
        await api.put(`/requests/${requestId}/move`, { collection_id: collectionId, folder_id: folderId })
        await fetchCollections()
    }

    async function reorderRequests(ids: string[]): Promise<void> {
        const api = useApi()
        await api.post('/requests/reorder', { ids })
    }

    async function reorderFolders(collectionId: string, ids: string[]): Promise<void> {
        const api = useApi()
        await api.post(`/collections/${collectionId}/folders/reorder`, { ids })
    }

    return {
        collections,
        loading,
        limitReached,
        fetchCollections,
        createCollection,
        updateCollection,
        deleteCollection,
        bulkDeleteCollections,
        saveRequest,
        deleteRequest,
        duplicateRequest,
        renameRequest,
        createFolder,
        updateFolder,
        deleteFolder,
        moveRequest,
        reorderRequests,
        reorderFolders,
    }
}
