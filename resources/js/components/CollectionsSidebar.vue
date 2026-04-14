<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import draggable from 'vuedraggable'
import { useCollections } from '../composables/useCollections'
import ContextMenu from './ContextMenu.vue'
import type { ContextMenuItem } from './ContextMenu.vue'
import type { ActiveRequest, Collection, Folder, SavedRequest, RequestData } from '../types'
import { parsePostmanCollection, exportToPostman, downloadJson } from '../postman'

const props = defineProps<{
    currentRequest: ActiveRequest
    features?:      string[]
}>()

const emit = defineEmits<{
    select:  [request: RequestData]
    upgrade: []
}>()

function hasFeature(feature: string): boolean {
    return (props.features ?? []).includes(feature)
}

// ── Import / Export ──────────────────────────────────────────────────────────
const importError   = ref<string | null>(null)
const importing     = ref(false)
const importInput   = ref<HTMLInputElement | null>(null)

function triggerImport(): void {
    if (!hasFeature('import_export')) {
        emit('upgrade')
        return
    }
    importError.value = null
    importInput.value?.click()
}

async function handleImportFile(e: Event): Promise<void> {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return

    importing.value   = true
    importError.value = null

    try {
        const text = await file.text()
        const json = JSON.parse(text)
        const parsed = parsePostmanCollection(json)

        // 1. Create the collection
        const result = await createCollection(parsed.name)
        if ('upgrade' in result) { emit('upgrade'); return }

        const colId = (result as { id: string }).id

        // 2. Create top-level requests
        for (const req of parsed.requests) {
            await saveRequest(colId, { name: req.name, data: req.data })
        }

        // 3. Create folders + their requests
        for (const folder of parsed.folders) {
            const f = await createFolder(colId, folder.name)
            for (const req of folder.requests) {
                await saveRequest(colId, { name: req.name, folder_id: f.id, data: req.data })
            }
        }

        await fetchCollections()
    } catch (err) {
        importError.value = err instanceof Error ? err.message : 'Import failed'
    } finally {
        importing.value = false
        // Reset so the same file can be re-imported
        if (importInput.value) importInput.value.value = ''
    }
}

function handleExport(col: Collection): void {
    const postman  = exportToPostman(col)
    const filename = `${col.name.replace(/[^a-z0-9]/gi, '_')}.postman_collection.json`
    downloadJson(postman, filename)
}

const {
    collections,
    loading,
    limitReached,
    fetchCollections,
    createCollection,
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
} = useCollections()

// ── Bulk select ───────────────────────────────────────────────────────────────
const selectMode    = ref(false)
const selectedIds   = ref<Set<string>>(new Set())
const bulkDeleting  = ref(false)

const selectedCount = computed(() => selectedIds.value.size)

function toggleSelectMode(): void {
    selectMode.value = !selectMode.value
    if (!selectMode.value) selectedIds.value = new Set()
}

function toggleSelect(id: string): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id)
    } else {
        selectedIds.value.add(id)
    }
    selectedIds.value = new Set(selectedIds.value)
}

async function handleBulkDelete(): Promise<void> {
    if (selectedIds.value.size === 0) return
    bulkDeleting.value = true
    try {
        await bulkDeleteCollections([...selectedIds.value])
        selectedIds.value = new Set()
        selectMode.value  = false
    } finally {
        bulkDeleting.value = false
    }
}

// ── Inline rename (request) ───────────────────────────────────────────────────
const renamingRequestId   = ref<string | null>(null)
const renamingRequestName = ref('')
const renamingCollectionId = ref<string | null>(null)

function startRenameRequest(req: SavedRequest, collectionId: string): void {
    renamingRequestId.value    = req.id
    renamingRequestName.value  = req.name
    renamingCollectionId.value = collectionId
}

async function commitRenameRequest(): Promise<void> {
    if (!renamingRequestId.value || !renamingCollectionId.value) return
    const name = renamingRequestName.value.trim()
    if (!name) { renamingRequestId.value = null; return }

    const req = (() => {
        for (const col of collections.value) {
            for (const r of col.requests) { if (r.id === renamingRequestId.value) return r }
            for (const f of col.folders) { for (const r of f.requests) { if (r.id === renamingRequestId.value) return r } }
        }
        return null
    })()

    if (req) {
        await renameRequest(renamingCollectionId.value, renamingRequestId.value, name, req.data)
    }
    renamingRequestId.value = null
}

const expanded       = ref<Set<string>>(new Set())
const newName        = ref('')
const showCreate     = ref(false)
const saving         = ref<string | null>(null)
const saveName       = ref('')
const showSaveIn     = ref<string | null>(null)

const showNewFolder       = ref<string | null>(null)
const newFolderName       = ref('')
const newFolderDesc       = ref('')
const editingFolder       = ref<Folder | null>(null)
const editFolderName      = ref('')
const editFolderDesc      = ref('')
const expandedFolders     = ref<Set<string>>(new Set())
const showSaveInFolder    = ref<string | null>(null)
const saveFolderName      = ref('')

function toggleExpand(id: string): void {
    if (expanded.value.has(id)) {
        expanded.value.delete(id)
    } else {
        expanded.value.add(id)
    }
}

function toggleExpandFolder(id: string): void {
    if (expandedFolders.value.has(id)) {
        expandedFolders.value.delete(id)
    } else {
        expandedFolders.value.add(id)
    }
}

async function handleCreate(): Promise<void> {
    const name = newName.value.trim()
    if (!name) return
    const result = await createCollection(name)
    if (result && 'upgrade' in result) {
        emit('upgrade')
        return
    }
    newName.value    = ''
    showCreate.value = false
}

async function handleDelete(id: string): Promise<void> {
    await deleteCollection(id)
    expanded.value.delete(id)
}

async function handleSaveRequest(collectionId: string): Promise<void> {
    const name = saveName.value.trim()
    if (!name) return

    saving.value = collectionId

    const headers: Record<string, string> = {}
    for (const h of props.currentRequest.headers) {
        if (h.enabled && h.key) headers[h.key] = h.value
    }

    const authData       = props.currentRequest.auth?.type !== 'none' ? props.currentRequest.auth : undefined
    const scriptData     = props.currentRequest.preRequestScript.trim() || undefined
    const assertionsData = props.currentRequest.assertions.length > 0 ? props.currentRequest.assertions : undefined

    await saveRequest(collectionId, {
        name,
        data: {
            method:            props.currentRequest.method,
            url:               props.currentRequest.url,
            headers:           Object.keys(headers).length > 0 ? headers : undefined,
            body:              props.currentRequest.body || undefined,
            auth:              authData,
            preRequestScript:  scriptData,
            assertions:        assertionsData,
        },
    })

    saving.value     = null
    saveName.value   = ''
    showSaveIn.value = null
    expanded.value.add(collectionId)
}

async function handleSaveInFolder(collectionId: string, folderId: string): Promise<void> {
    const name = saveFolderName.value.trim()
    if (!name) return

    saving.value = folderId

    const headers: Record<string, string> = {}
    for (const h of props.currentRequest.headers) {
        if (h.enabled && h.key) headers[h.key] = h.value
    }

    const authData       = props.currentRequest.auth?.type !== 'none' ? props.currentRequest.auth : undefined
    const scriptData     = props.currentRequest.preRequestScript.trim() || undefined
    const assertionsData = props.currentRequest.assertions.length > 0 ? props.currentRequest.assertions : undefined

    await saveRequest(collectionId, {
        name,
        folder_id: folderId,
        data: {
            method:           props.currentRequest.method,
            url:              props.currentRequest.url,
            headers:          Object.keys(headers).length > 0 ? headers : undefined,
            body:             props.currentRequest.body || undefined,
            auth:             authData,
            preRequestScript: scriptData,
            assertions:       assertionsData,
        },
    })

    saving.value          = null
    saveFolderName.value  = ''
    showSaveInFolder.value = null
    expandedFolders.value.add(folderId)
}

async function handleCreateFolder(collectionId: string): Promise<void> {
    const name = newFolderName.value.trim()
    if (!name) return
    const folder = await createFolder(collectionId, name, newFolderDesc.value.trim() || undefined)
    newFolderName.value   = ''
    newFolderDesc.value   = ''
    showNewFolder.value   = null
    expandedFolders.value.add(folder.id)
}

async function handleUpdateFolder(): Promise<void> {
    if (!editingFolder.value) return
    const name = editFolderName.value.trim()
    if (!name) return
    await updateFolder(
        editingFolder.value.collection_id,
        editingFolder.value.id,
        name,
        editFolderDesc.value.trim() || undefined,
    )
    editingFolder.value = null
}

async function handleDeleteFolder(collectionId: string, folderId: string): Promise<void> {
    await deleteFolder(collectionId, folderId)
    expandedFolders.value.delete(folderId)
}

function startEditFolder(folder: Folder): void {
    editingFolder.value  = folder
    editFolderName.value = folder.name
    editFolderDesc.value = folder.description ?? ''
}

type DraggableChange<T> = {
    moved?:   { element: T; oldIndex: number; newIndex: number }
    added?:   { element: T; newIndex: number }
    removed?: { element: T; oldIndex: number }
}

async function handleFolderReorder(col: Collection): Promise<void> {
    await reorderFolders(col.id, col.folders.map(f => f.id))
}

async function handleRequestChange(
    event: DraggableChange<SavedRequest>,
    collectionId: string,
    folderId: string | null,
    list: SavedRequest[],
): Promise<void> {
    if (event.moved) {
        await reorderRequests(list.map(r => r.id))
    } else if (event.added) {
        await moveRequest(event.added.element.id, collectionId, folderId)
    }
}

const menuVisible = ref(false)
const menuX       = ref(0)
const menuY       = ref(0)
const menuItems   = ref<ContextMenuItem[]>([])

function openMenu(e: MouseEvent, items: ContextMenuItem[]): void {
    e.stopPropagation()
    menuX.value     = e.clientX
    menuY.value     = e.clientY
    menuItems.value = items
    menuVisible.value = true
}

function collectionMenuItems(col: Collection): ContextMenuItem[] {
    return [
        {
            label:  'Save request here',
            icon:   '↓',
            action: () => { showSaveIn.value = col.id; saveName.value = '' },
        },
        {
            label:  'New folder',
            icon:   '⊕',
            action: () => { showNewFolder.value = col.id; newFolderName.value = ''; newFolderDesc.value = '' },
        },
        {
            label:  hasFeature('import_export') ? 'Export as Postman' : 'Export as Postman (Pro)',
            icon:   '↑',
            action: () => hasFeature('import_export') ? handleExport(col) : emit('upgrade'),
        },
        {
            label:  'Delete collection',
            icon:   '×',
            danger: true,
            action: () => handleDelete(col.id),
        },
    ]
}

function requestMenuItems(req: SavedRequest, collectionId: string): ContextMenuItem[] {
    return [
        {
            label:  'Rename',
            icon:   '✎',
            action: () => startRenameRequest(req, collectionId),
        },
        {
            label:  'Duplicate',
            icon:   '⎘',
            action: () => duplicateRequest(req.id),
        },
        {
            label:  'Delete',
            icon:   '×',
            danger: true,
            action: () => deleteRequest(req.id),
        },
    ]
}

function folderMenuItems(col: Collection, folder: Folder): ContextMenuItem[] {
    return [
        {
            label:  'Save request here',
            icon:   '↓',
            action: () => { showSaveInFolder.value = folder.id; saveFolderName.value = '' },
        },
        {
            label:  'Edit folder',
            icon:   '✎',
            action: () => startEditFolder(folder),
        },
        {
            label:  'Delete folder',
            icon:   '×',
            danger: true,
            action: () => handleDeleteFolder(col.id, folder.id),
        },
    ]
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

onMounted(fetchCollections)
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Hidden file input for import -->
        <input
            ref="importInput"
            type="file"
            accept=".json,application/json"
            class="hidden"
            @change="handleImportFile"
        />

        <!-- Header -->
        <div class="px-3 py-2 border-b border-gray-800 flex items-center justify-between">
            <span class="text-xs text-gray-500">
                {{ collections.length }}<span v-if="!limitReached"> / 5</span>
            </span>
            <div class="flex items-center gap-2">
                <!-- Bulk delete mode -->
                <template v-if="selectMode">
                    <button
                        class="text-xs text-red-400 hover:text-red-300 disabled:opacity-40 transition-colors"
                        :disabled="selectedCount === 0 || bulkDeleting"
                        @click="handleBulkDelete"
                    >Delete {{ selectedCount > 0 ? `(${selectedCount})` : '' }}</button>
                    <button
                        class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                        @click="toggleSelectMode"
                    >Cancel</button>
                </template>
                <template v-else>
                    <button
                        v-if="limitReached"
                        class="text-xs text-amber-400 hover:text-amber-300 transition-colors"
                        @click="emit('upgrade')"
                    >Upgrade for more</button>
                    <template v-else>
                        <button
                            v-if="collections.length > 0"
                            class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                            title="Select collections to delete"
                            @click="toggleSelectMode"
                        >Select</button>
                        <button
                            class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                            :disabled="importing"
                            title="Import Postman collection"
                            @click="triggerImport"
                        >Import</button>
                        <button
                            class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
                            @click="showCreate = !showCreate"
                        >+ New</button>
                    </template>
                </template>
            </div>
        </div>

        <!-- Import error -->
        <div
            v-if="importError"
            class="px-3 py-2 text-xs text-red-400 border-b border-gray-800 bg-red-900/10 flex items-start gap-2"
        >
            <span class="shrink-0">!</span>
            <span class="break-words">{{ importError }}</span>
            <button class="ml-auto shrink-0 text-gray-600 hover:text-gray-400" @click="importError = null">×</button>
        </div>

        <!-- Create form -->
        <div v-if="showCreate" class="px-3 py-2 border-b border-gray-800 flex gap-2">
            <input
                v-model="newName"
                type="text"
                placeholder="Collection name"
                class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                @keydown.enter="handleCreate"
                @keydown.esc="showCreate = false"
            />
            <button
                class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                :disabled="!newName.trim()"
                @click="handleCreate"
            >Save</button>
        </div>

        <div v-if="loading" class="flex-1 flex items-center justify-center text-gray-600 text-xs">Loading…</div>

        <div
            v-else-if="collections.length === 0"
            class="flex-1 flex items-center justify-center text-gray-700 text-xs text-center px-4"
        >
            No collections yet.<br />Create one to save requests.
        </div>

        <!-- Collections list -->
        <div v-else class="flex-1 overflow-y-auto">
            <div v-for="col in collections" :key="col.id">
                <!-- Collection header -->
                <div
                    class="flex items-center gap-1 px-2 py-1.5 hover:bg-gray-800/40 cursor-pointer group"
                    @click="selectMode ? toggleSelect(col.id) : toggleExpand(col.id)"
                >
                    <!-- Checkbox in select mode -->
                    <input
                        v-if="selectMode"
                        type="checkbox"
                        :checked="selectedIds.has(col.id)"
                        class="shrink-0 accent-indigo-500"
                        @click.stop="toggleSelect(col.id)"
                    />
                    <svg
                        v-else
                        class="w-3 h-3 text-gray-600 shrink-0 transition-transform"
                        :class="expanded.has(col.id) ? '' : '-rotate-90'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span class="flex-1 text-xs text-gray-300 font-medium truncate mx-1">{{ col.name }}</span>
                    <span class="text-xs text-gray-600 tabular-nums">
                        {{ col.requests.length + col.folders.reduce((s, f) => s + f.requests.length, 0) }}
                    </span>
                    <button
                        v-if="!selectMode"
                        class="ml-1 p-1 rounded text-gray-500 hover:text-white hover:bg-gray-700 transition-colors shrink-0 opacity-0 group-hover:opacity-100"
                        title="Collection options"
                        @click.stop="openMenu($event, collectionMenuItems(col))"
                    >
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </button>
                </div>

                <!-- New folder form -->
                <div v-if="showNewFolder === col.id" class="px-3 pb-2 flex flex-col gap-1.5" @click.stop>
                    <input
                        v-model="newFolderName"
                        type="text"
                        placeholder="Folder name"
                        class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                        @keydown.enter="handleCreateFolder(col.id)"
                        @keydown.esc="showNewFolder = null"
                    />
                    <input
                        v-model="newFolderDesc"
                        type="text"
                        placeholder="Description (optional)"
                        class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                        @keydown.enter="handleCreateFolder(col.id)"
                        @keydown.esc="showNewFolder = null"
                    />
                    <div class="flex gap-2">
                        <button
                            class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                            :disabled="!newFolderName.trim()"
                            @click="handleCreateFolder(col.id)"
                        >Create</button>
                        <button class="text-xs text-gray-500 hover:text-gray-300" @click="showNewFolder = null">Cancel</button>
                    </div>
                </div>

                <!-- Save-here form -->
                <div v-if="showSaveIn === col.id" class="flex gap-2 px-3 pb-2" @click.stop>
                    <input
                        v-model="saveName"
                        type="text"
                        placeholder="Request name"
                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                        @keydown.enter="handleSaveRequest(col.id)"
                        @keydown.esc="showSaveIn = null"
                    />
                    <button
                        class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                        :disabled="!saveName.trim() || saving === col.id"
                        @click="handleSaveRequest(col.id)"
                    >{{ saving === col.id ? '…' : 'Save' }}</button>
                </div>

                <!-- Collection contents -->
                <div v-if="expanded.has(col.id)">
                    <!-- Folders draggable — hold 200ms to drag -->
                    <draggable
                        :list="col.folders"
                        item-key="id"
                        :delay="200"
                        ghost-class="opacity-30"
                        :animation="150"
                        @end="handleFolderReorder(col)"
                    >
                        <template #item="{ element: folder }">
                            <div>
                                <!-- Folder header -->
                                <div
                                    class="flex items-center gap-1.5 pl-4 pr-2 py-1.5 hover:bg-gray-800/40 cursor-grab active:cursor-grabbing group/folder"
                                    @click="toggleExpandFolder(folder.id)"
                                >
                                    <svg
                                        class="w-3 h-3 text-gray-600 shrink-0 transition-transform"
                                        :class="expandedFolders.has(folder.id) ? '' : '-rotate-90'"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <svg class="w-3 h-3 text-indigo-400/60 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs text-gray-300 font-medium truncate block">{{ folder.name }}</span>
                                        <span v-if="folder.description" class="text-xs text-gray-600 truncate block leading-tight">{{ folder.description }}</span>
                                    </div>
                                    <span class="text-xs text-gray-700 tabular-nums">{{ folder.requests.length }}</span>
                                    <button
                                        class="ml-1 p-1 rounded text-gray-500 hover:text-white hover:bg-gray-700 transition-colors shrink-0 opacity-0 group-hover/folder:opacity-100"
                                        title="Folder options"
                                        @click.stop="openMenu($event, folderMenuItems(col, folder))"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Save-in-folder form -->
                                <div v-if="showSaveInFolder === folder.id" class="flex gap-2 pl-8 pr-3 pb-2" @click.stop>
                                    <input
                                        v-model="saveFolderName"
                                        type="text"
                                        placeholder="Request name"
                                        class="flex-1 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 min-w-0"
                                        @keydown.enter="handleSaveInFolder(col.id, folder.id)"
                                        @keydown.esc="showSaveInFolder = null"
                                    />
                                    <button
                                        class="text-xs text-indigo-400 hover:text-indigo-300 disabled:opacity-40"
                                        :disabled="!saveFolderName.trim() || saving === folder.id"
                                        @click="handleSaveInFolder(col.id, folder.id)"
                                    >{{ saving === folder.id ? '…' : 'Save' }}</button>
                                </div>

                                <!-- Folder requests — hold 200ms to drag / cross-list -->
                                <div v-if="expandedFolders.has(folder.id)">
                                    <div v-if="folder.requests.length === 0" class="pl-10 pr-3 py-1.5 text-xs text-gray-700 italic">Empty</div>
                                    <draggable
                                        :list="folder.requests"
                                        item-key="id"
                                        group="requests"
                                        :delay="200"
                                        ghost-class="opacity-30"
                                        :animation="150"
                                        @change="(e: DraggableChange<SavedRequest>) => handleRequestChange(e, col.id, folder.id, folder.requests)"
                                    >
                                        <template #item="{ element: req }">
                                            <div
                                                class="flex items-center gap-2 pl-7 pr-3 py-1.5 hover:bg-gray-800/50 cursor-grab active:cursor-grabbing group/req"
                                                @click="emit('select', req.data)"
                                            >
                                                <span
                                                    class="text-xs font-mono font-semibold w-12 shrink-0"
                                                    :class="METHOD_COLORS[req.data.method?.toUpperCase()] ?? 'text-gray-400'"
                                                >{{ req.data.method }}</span>
                                                <!-- Inline rename -->
                                                <input
                                                    v-if="renamingRequestId === req.id"
                                                    v-model="renamingRequestName"
                                                    class="flex-1 bg-gray-900 border border-indigo-500 rounded px-1 py-0 text-xs text-gray-200 focus:outline-none min-w-0"
                                                    @click.stop
                                                    @keydown.enter.stop="commitRenameRequest"
                                                    @keydown.esc.stop="renamingRequestId = null"
                                                    @blur="commitRenameRequest"
                                                />
                                                <span v-else class="flex-1 text-xs text-gray-400 truncate">{{ req.name }}</span>
                                                <button
                                                    class="p-1 rounded text-gray-500 hover:text-white hover:bg-gray-700 transition-colors shrink-0 opacity-0 group-hover/req:opacity-100"
                                                    title="Request options"
                                                    @click.stop="openMenu($event, requestMenuItems(req, col.id))"
                                                >
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </draggable>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <!-- Top-level requests — hold 200ms to drag / cross-list -->
                    <div v-if="col.requests.length === 0 && col.folders.length === 0" class="px-6 py-1.5 text-xs text-gray-700 italic">Empty</div>
                    <draggable
                        :list="col.requests"
                        item-key="id"
                        group="requests"
                        :delay="200"
                        ghost-class="opacity-30"
                        :animation="150"
                        @change="(e: DraggableChange<SavedRequest>) => handleRequestChange(e, col.id, null, col.requests)"
                    >
                        <template #item="{ element: req }">
                            <div
                                class="flex items-center gap-2 px-4 py-1.5 hover:bg-gray-800/50 cursor-grab active:cursor-grabbing group/req"
                                @click="emit('select', req.data)"
                            >
                                <span
                                    class="text-xs font-mono font-semibold w-12 shrink-0"
                                    :class="METHOD_COLORS[req.data.method?.toUpperCase()] ?? 'text-gray-400'"
                                >{{ req.data.method }}</span>
                                <!-- Inline rename -->
                                <input
                                    v-if="renamingRequestId === req.id"
                                    v-model="renamingRequestName"
                                    class="flex-1 bg-gray-900 border border-indigo-500 rounded px-1 py-0 text-xs text-gray-200 focus:outline-none min-w-0"
                                    @click.stop
                                    @keydown.enter.stop="commitRenameRequest"
                                    @keydown.esc.stop="renamingRequestId = null"
                                    @blur="commitRenameRequest"
                                />
                                <span v-else class="flex-1 text-xs text-gray-400 truncate">{{ req.name }}</span>
                                <button
                                    class="p-1 rounded text-gray-500 hover:text-white hover:bg-gray-700 transition-colors shrink-0 opacity-0 group-hover/req:opacity-100"
                                    title="Request options"
                                    @click.stop="openMenu($event, requestMenuItems(req, col.id))"
                                >
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </draggable>
                </div>
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

    <!-- Edit folder modal -->
    <Teleport v-if="editingFolder" to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
            @mousedown.self="editingFolder = null"
        >
            <div class="bg-gray-900 border border-gray-700 rounded-lg shadow-2xl w-80 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                    <span class="text-xs font-semibold text-white">Edit folder</span>
                    <button class="text-gray-600 hover:text-gray-400 text-lg leading-none" @click="editingFolder = null">×</button>
                </div>
                <div class="p-4 flex flex-col gap-2">
                    <input
                        v-model="editFolderName"
                        type="text"
                        placeholder="Folder name"
                        class="bg-gray-950 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                        @keydown.enter="handleUpdateFolder"
                        @keydown.esc="editingFolder = null"
                    />
                    <input
                        v-model="editFolderDesc"
                        type="text"
                        placeholder="Description (optional)"
                        class="bg-gray-950 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                        @keydown.enter="handleUpdateFolder"
                        @keydown.esc="editingFolder = null"
                    />
                </div>
                <div class="px-4 pb-4 flex justify-end gap-2">
                    <button class="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200 transition-colors" @click="editingFolder = null">Cancel</button>
                    <button
                        class="px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors disabled:opacity-50"
                        :disabled="!editFolderName.trim()"
                        @click="handleUpdateFolder"
                    >Save</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
