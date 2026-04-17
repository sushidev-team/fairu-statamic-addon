<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, getCurrentInstance, defineComponent, h } from 'vue';
import { toast, progress } from '@statamic/cms/api';
import { Stack, Button, Input, Icon, Checkbox, Pagination, ToggleGroup, ToggleItem, Listing, ListingTable, Panel, DropdownItem } from '@statamic/cms/ui';
import Dropzone from './Dropzone.vue';
import BrowserListItem from './browser/BrowserListItem.vue';
import Folder from './browser/Folder.vue';
import FairuAssetEditor from './FairuAssetEditor.vue';
import FairuAssetActions from './FairuAssetActions.vue';
import FairuPreview from './FairuPreview.vue';
import { fairuLoadFolder, fairuUpload, fairuCreateFolder, fairuLoadFilesMeta } from '../utils/fetches';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const FairuStackHeader = defineComponent({
    name: 'StackHeader',
    setup(_, { slots }) {
        return () => h('div', {
            class: 'flex items-center justify-between rounded-t-xl border-b border-gray-300 ps-6 pe-4 py-2 dark:border-gray-950 dark:bg-gray-800',
        }, slots.default?.());
    },
});

const props = defineProps({
    meta: Object,
    config: Object,
    initialAssets: Array,
    selectionType: {
        type: String,
        default: 'files',
    },
    multiselect: Boolean,
    canUpload: Boolean,
    startFolder: { type: String, default: null },
});

const emit = defineEmits(['close', 'selected']);

const uploadInput = ref(null);
const searchQuery = ref('');

const assets = ref([]);
const loading = ref(false);
const folder = ref(null);
const page = ref(1);
const displayType = ref('list');
const perPage = ref(25);
const loadingList = ref(false);
const showSelection = ref(false);
const percentUploaded = ref(0);
const folderContent = ref(null);
const createFolderInputVisible = ref(false);
const newFolderName = ref('');
const previewItem = ref(null);
const editingAssetId = ref(null);
const actionsRef = ref(null);


let searchTimer = null;

function emitClose() {
    emit('close');
}

function openFile() {
    uploadInput.value.value = null;
    uploadInput.value.click();
}

function openCreateFolder() {
    createFolderInputVisible.value = true;
}

function closeCreateFolder() {
    createFolderInputVisible.value = false;
    newFolderName.value = '';
}

function selectFolder(folderId) {
    page.value = 1;
    loadFolderContent(null, folderId);
    searchQuery.value = '';
}

function selectItem(asset) {
    emit('selected', asset);
    nextTick(() => emitClose());
}

function sendSelection() {
    if (props.selectionType === 'folder') {
        emit('selected', folder.value);
    } else if (props.multiselect) {
        emit('selected', assets.value);
    } else {
        emit('selected', assets.value?.[0] ?? null);
    }
    nextTick(() => emitClose());
}

function toggleItemSelection(asset) {
    const idx = assets.value.findIndex((e) => e?.id === asset.id);
    if (idx > -1) {
        assets.value = assets.value.filter((e) => e?.id !== asset.id);
    } else {
        assets.value.push(asset);
    }
    if (assets.value.length < 1) showSelection.value = false;
}

function toggleShowSelection() {
    showSelection.value = !showSelection.value;
}

function clearSelection() {
    assets.value = [];
    showSelection.value = false;
}

function toggleCurrentSelection() {
    if (showSelection.value) {
        clearSelection();
        return;
    }

    const folderFiles = folderContent.value?.data.filter((e) => e.type !== 'folder') || [];
    const allSelected = folderFiles.every((file) => assets.value.some((e) => e.id === file.id));
    const maxReached = props.config.max_files && assets.value.length >= props.config.max_files;

    if (allSelected || maxReached) {
        const folderFileIds = folderFiles.map((f) => f.id);
        assets.value = assets.value.filter((a) => !folderFileIds.includes(a.id));
    } else {
        const newItems = folderFiles.filter((file) => !assets.value.some((e) => e.id === file.id));
        if (!newItems.length) return;
        const remainingSlots = Number.isFinite(props.config.max_files)
            ? Math.max(0, props.config.max_files - assets.value.length)
            : newItems.length;
        assets.value.push(...newItems.slice(0, remainingSlots));
    }
}

function setPreview(itemIndex) {
    previewItem.value = itemIndex;
}

function closePreview() {
    previewItem.value = null;
}

function editPreview(item) {
    if (!item?.id) return;
    previewItem.value = null;
    openEditor(item);
}

function handlePreviewConfirm(item) {
    if (!item) return;
    selectItem(item);
}

function handlePreviewToggle(item) {
    if (!item) return;
    toggleItemSelection(item);
}

function handlePreviewApply() {
    previewItem.value = null;
    sendSelection();
}

function openEditor(asset) {
    if (!asset?.id) return;
    editingAssetId.value = asset.id;
}

function closeEditor() {
    editingAssetId.value = null;
}

async function handleAssetSaved(updated) {
    if (!updated?.id) return;
    const refreshed = await loadMetaData([updated.id]);
    const refreshedAsset = refreshed?.[0];
    if (!refreshedAsset) return;
    patchAsset(refreshedAsset.id, refreshedAsset);
}

function patchAsset(id, patch) {
    if (folderContent.value?.data) {
        folderContent.value.data = folderContent.value.data.map((e) =>
            e?.id === id ? { ...e, ...patch } : e,
        );
    }
    assets.value = assets.value.map((e) =>
        e?.id === id ? { ...e, ...patch } : e,
    );
}

function removeAssetFromView(id) {
    if (folderContent.value?.data) {
        folderContent.value.data = folderContent.value.data.filter((e) => e?.id !== id);
    }
    assets.value = assets.value.filter((e) => e?.id !== id);
}

function openRename(asset) {
    actionsRef.value?.openRename(asset);
}

function openMove(asset) {
    actionsRef.value?.openMove(asset);
}

function openDelete(asset) {
    actionsRef.value?.openDelete(asset);
}

function handleAssetRenamed({ id, name, asset }) {
    patchAsset(id, { name, ...(asset || {}) });
}

async function handleAssetMoved({ id }) {
    removeAssetFromView(id);
    await loadFolderContent(searchQuery.value);
}

function handleAssetDeleted({ id }) {
    removeAssetFromView(id);
}


function isSelected(asset) {
    return assets.value?.some((e) => e?.id === asset.id);
}

function handleFileChange(evt) {
    handleUploadFiles(evt.target.files);
}

function handleFileDrop(files) {
    if (!files) return;
    handleUploadFiles(files);
}

function handleUploadFiles(files) {
    progress.start('browser-upload');
    percentUploaded.value = 0;
    loading.value = true;

    fairuUpload({
        files,
        folder: folder.value?.id ?? null,
        onUploadProgressCallback: (progressEvent) => {
            percentUploaded.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        },
        successCallback: async (result) => {
            progress.complete('browser-upload');
            toast.success(__('fairu::browser.files_uploaded_successfully'));

            const newIds = result?.data?.map((e) => e.id) || [];
            const fetchedAssets = await loadMetaData(newIds);

            if (props.multiselect) {
                if (fetchedAssets?.length > 0) {
                    const remainingSlots = Number.isFinite(props.config.max_files)
                        ? Math.max(0, props.config.max_files - assets.value.length)
                        : fetchedAssets.length;
                    assets.value.push(...fetchedAssets.slice(0, remainingSlots));
                }
                await loadFolderContent();
            } else {
                assets.value = fetchedAssets?.slice(0, 1) || [];
                if (assets.value[0]) selectItem(assets.value[0]);
            }
            loading.value = false;
        },
        errorCallback: (err) => {
            loading.value = false;
            progress.complete('browser-upload');
            toast.error(err?.response?.data?.message || __('fairu::browser.errors.upload_failed'));
            if (uploadInput.value) uploadInput.value.value = null;
        },
    });
}

async function handleCreateFolder() {
    loading.value = true;
    try {
        await fairuCreateFolder({
            name: newFolderName.value,
            folder: folder.value?.id ?? null,
        });
        closeCreateFolder();
        await loadFolderContent();
    } catch (error) {
        toast.error(error?.message || __('fairu::browser.errors.folder_creation_failed'));
        loading.value = false;
    }
}

function handleSearchInput(value) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadFolderContent(value);
    }, 250);
}

function handlePageSelected(p) {
    page.value = p;
    loadFolderContent(searchQuery.value);
}

async function loadFolderContent(search, folderId) {
    loadingList.value = true;
    let retriesAvailable = 1;

    await fairuLoadFolder({
        page: page.value,
        folder: folderId !== undefined ? folderId : folder.value?.id,
        search: search ?? null,
        successCallback: (result) => {
            folder.value = result.data.entry;
            folderContent.value = result.data.entries;
            loadingList.value = false;
        },
        errorCallback: async () => {
            if (folderId && retriesAvailable > 0) {
                retriesAvailable -= 1;
                await loadFolderContent(search, folderId);
                return;
            }
            toast.error(__('fairu::browser.errors.error_accessing_folder'));
            folderContent.value = null;
            folder.value = null;
            loadingList.value = false;
        },
    });
}

async function loadMetaData(ids) {
    if (!ids?.length) return [];

    const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);
    if (assetIds.length === 0) return [];

    loading.value = true;

    try {
        return await fairuLoadFilesMeta(assetIds);
    } catch (err) {
        console.error(__('fairu::browser.errors.error_fetching_files'), err);
        return [];
    } finally {
        loading.value = false;
    }
}

function getExtension(mime) {
    const parts = mime.split('/');
    return parts.length === 2 ? parts[1] : 'n/a';
}

const isFolderMode = computed(() => props.selectionType === 'folder');
const listingColumns = [{ field: 'name', label: __('Name'), sortable: false }];

const folderItems = computed(() =>
    folderContent.value?.data?.filter((e) => e?.type === 'folder') || []
);

const fileItems = computed(() =>
    folderContent.value?.data?.filter((e) => e?.type !== 'folder') || []
);

const allListingItems = computed(() => {
    if (showSelection.value) return assets.value;

    const items = [];

    // "Go back" parent folder
    if (folder.value) {
        items.push({ id: '__parent__', name: '...', type: 'folder', _isParent: true });
    }

    // Folders then files
    items.push(...folderItems.value);
    items.push(...fileItems.value);

    return items;
});

const selectedIds = computed(() =>
    assets.value?.map((a) => a.id) || []
);

const folderIds = computed(() => {
    const ids = new Set(folderItems.value.map((f) => f.id));
    if (folder.value) ids.add('__parent__');
    return ids;
});

const maxSelectionsCount = computed(() => {
    if (!props.multiselect) return 1;
    return props.config.max_files || Infinity;
});

function isMediaItem(item) {
    return item?.mime?.startsWith('image/') || item?.mime?.startsWith('video/');
}

function thumbnailUrl(item, size = 50) {
    return `${props.meta.proxy}/${item.id}/thumbnail.webp?width=${size}&height=${size}`;
}

function getFileIndex(row) {
    return fileItems.value.findIndex((f) => f.id === row.id);
}

function handleListingSelections(newSelections) {
    // Filter out folder IDs — folders are not selectable
    const fileOnlySelections = newSelections.filter((id) => !folderIds.value.has(id));

    if (!props.multiselect) {
        const newId = fileOnlySelections.find((id) => !selectedIds.value.includes(id));
        if (newId) {
            const item = fileItems.value.find((f) => f.id === newId) ||
                         assets.value.find((a) => a.id === newId);
            if (item) selectItem(item);
        }
        return;
    }

    const source = showSelection.value ? assets.value : fileItems.value;
    const currentFileIds = new Set(source.map((f) => f.id));
    const assetsFromOtherPages = assets.value.filter((a) => !currentFileIds.has(a.id));
    const newlySelected = source.filter((f) => fileOnlySelections.includes(f.id));

    assets.value = [...assetsFromOtherPages, ...newlySelected];
    if (assets.value.length < 1) showSelection.value = false;
}

const breadcrumbSegments = computed(() => {
    const path = folder.value?.folder_path;
    if (!path || typeof path !== 'string') {
        if (folder.value?.name) return [folder.value.name];
        return [];
    }
    return path.split('/').map((s) => s.trim()).filter((s) => s.length > 0);
});

const parentFolderId = computed(() => folder.value?.parent_id ?? null);

const previewImage = computed(() => {
    if (previewItem.value === null || previewItem.value === undefined) return null;
    return fileItems.value[previewItem.value];
});

onMounted(async () => {
    displayType.value = props.config.display_type || 'list';
    assets.value =
        props.config.max_files === 1
            ? []
            : [...(props.initialAssets?.length > 0 ? props.initialAssets : [])];
    const startFolder = props.startFolder || props.config.folder || props.meta?.folder || null;
    try {
        await loadFolderContent(null, startFolder);
    } catch (error) {
        toast.error(__('fairu::browser.errors.error_loading_folder'));
        await loadFolderContent(null, null);
    }
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }
});
</script>

<template>
    <FairuAssetEditor
        v-if="editingAssetId"
        :assetId="editingAssetId"
        :meta="meta"
        @close="closeEditor"
        @saved="handleAssetSaved"
        @renamed="handleAssetRenamed"
        @moved="handleAssetMoved"
        @deleted="handleAssetDeleted" />
    <FairuAssetActions
        ref="actionsRef"
        :meta="meta"
        :config="config"
        @renamed="handleAssetRenamed"
        @moved="handleAssetMoved"
        @deleted="handleAssetDeleted" />
    <Stack open @closed="emitClose" inset :wrap-slot="false">
        <!-- Custom header with Fairu logo -->
        <FairuStackHeader>
            <a href="https://fairu.app" target="_blank" class="flex items-center">
                <img class="w-16 h-auto" src="../../svg/fairu-logo.svg" alt="Fairu" />
            </a>
            <div class="flex items-center gap-2">
                <input class="hidden" type="file" ref="uploadInput" @change="handleFileChange" />
                <template v-if="createFolderInputVisible">
                    <Input
                        size="sm"
                        icon="asset-folder"
                        :focus="true"
                        :placeholder="__('fairu::browser.new_folder_name')"
                        :model-value="newFolderName"
                        @update:model-value="newFolderName = $event"
                        @keyup.enter="handleCreateFolder"
                        @keyup.esc="closeCreateFolder" />
                    <Button
                        variant="primary"
                        size="sm"
                        :text="__('fairu::browser.create')"
                        @click="handleCreateFolder" />
                    <Button
                        size="sm"
                        :text="__('fairu::browser.cancel')"
                        @click="closeCreateFolder" />
                </template>
                <template v-else>
                    <Input
                        icon="magnifying-glass"
                        size="sm"
                        clearable
                        :placeholder="__('fairu::browser.search_in_folder')"
                        :model-value="searchQuery"
                        @update:model-value="searchQuery = $event; handleSearchInput($event)"
                        @keyup.esc="searchQuery = ''; handleSearchInput('')" />
                    <Button
                        v-if="selectionType !== 'folder' && canUpload"
                        size="sm"
                        icon="upload"
                        :text="__('fairu::browser.upload')"
                        @click="openFile()" />
                    <Button
                        v-if="canUpload"
                        size="sm"
                        icon="asset-folder"
                        :text="__('fairu::browser.new_folder')"
                        @click="openCreateFolder" />
                </template>
                <Button icon="x" variant="ghost" class="-me-2" @click="emitClose" />
            </div>
        </FairuStackHeader>

        <!-- Breadcrumbs -->
        <div class="flex items-center gap-1 border-b border-gray-200 px-4 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
            <Button
                size="xs"
                variant="ghost"
                icon="arrow-up"
                round
                :disabled="!folder"
                :title="__('fairu::browser.go_up')"
                @click="selectFolder(parentFolderId)" />
            <nav class="flex flex-wrap items-center gap-1 min-w-0">
                <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                    :class="{ 'font-semibold !text-gray-900 dark:!text-gray-100': !folder }"
                    @click="folder ? selectFolder(null) : null">
                    {{ __('fairu::browser.root') }}
                </button>
                <template v-for="(segment, idx) in breadcrumbSegments" :key="idx + '-' + segment">
                    <Icon name="chevron-right" class="size-3 shrink-0 text-gray-400" />
                    <span
                        class="truncate px-1.5 py-0.5"
                        :class="idx === breadcrumbSegments.length - 1
                            ? 'font-semibold text-gray-900 dark:text-gray-100'
                            : 'text-gray-500 dark:text-gray-400'">
                        {{ segment }}
                    </span>
                </template>
            </nav>
        </div>

        <!-- Content area -->
        <dropzone
            :enabled="canUpload"
            @dropped="handleFileDrop"
            class="flex-1 overflow-y-auto p-4">
            <div
                v-if="loadingList"
                class="grid items-center justify-center w-full h-full p-8">
                <svg class="animate-spin size-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>

            <!-- LIST MODE -->
            <Listing
                v-if="!loadingList && displayType === 'list'"
                :items="allListingItems"
                :columns="listingColumns"
                :selections="multiselect ? selectedIds : undefined"
                :max-selections="multiselect ? maxSelectionsCount : undefined"
                :allow-search="false"
                :allow-customizing-columns="false"
                :allow-bulk-actions="false"
                :allow-presets="false"
                :sortable="false"
                :per-page="999"
                @update:selections="handleListingSelections"
                v-slot="{ items }">
                <Panel v-if="items.length" class="relative !mb-0">
                    <!-- Toolbar overlaid on the table header row -->
                    <div class="absolute top-1.75 right-1.75 z-10 flex items-center gap-2 px-3 h-[2.375rem]">
                        <Button
                            v-if="multiselect && assets?.length > 0"
                            size="xs"
                            :variant="showSelection ? 'filled' : 'ghost'"
                            @click="toggleShowSelection">
                            {{ __('fairu::browser.only_selection') }} ({{ assets?.length }})
                        </Button>
                        <ToggleGroup
                            variant="ghost"
                            size="xs"
                            :model-value="displayType"
                            @update:model-value="displayType = $event">
                            <ToggleItem icon="layout-list" value="list" />
                            <ToggleItem icon="layout-grid" value="tiles" />
                        </ToggleGroup>
                    </div>
                    <ListingTable>
                        <template #cell-name="{ row, value }">
                            <!-- Folder row -->
                            <button
                                v-if="row.type === 'folder'"
                                class="flex items-center gap-2 w-full cursor-pointer select-none -my-3 py-3"
                                @click.stop="selectFolder(row._isParent ? folder?.parent_id : row.id)">
                                <Icon name="folder" class="size-5 text-gray-500" />
                                <span>{{ value }}</span>
                            </button>
                            <!-- File row (disabled in folder mode) -->
                            <div
                                v-else-if="isFolderMode"
                                class="flex items-center gap-2 w-full -my-3 py-3 opacity-40 pointer-events-none">
                                <div class="shrink-0 size-7 rounded-sm overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <img
                                        v-if="meta.proxy && row?.blocked !== true && isMediaItem(row)"
                                        draggable="false"
                                        :src="thumbnailUrl(row)"
                                        class="size-full object-cover" />
                                    <i v-else class="material-symbols-outlined text-gray-400 dark:text-gray-600 text-base">description</i>
                                </div>
                                <span class="truncate">{{ value }}</span>
                            </div>
                            <!-- File row (single select) -->
                            <div
                                v-else-if="!multiselect"
                                class="flex items-center gap-2 w-full select-none -my-3 py-3">
                                <button
                                    class="flex items-center gap-2 grow min-w-0 cursor-pointer text-left"
                                    @click.stop="selectItem(row)">
                                    <div class="shrink-0 size-7 rounded-sm overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <img
                                            v-if="meta.proxy && row?.blocked !== true && isMediaItem(row)"
                                            draggable="false"
                                            :src="thumbnailUrl(row)"
                                            class="size-full object-cover" />
                                        <i v-else class="material-symbols-outlined text-gray-400 dark:text-gray-600 text-base">description</i>
                                    </div>
                                    <span class="truncate">{{ value }}</span>
                                </button>
                                <Button
                                    icon="pencil"
                                    variant="ghost"
                                    size="xs"
                                    round
                                    :title="__('fairu::fieldtype.edit')"
                                    @click.stop="openEditor(row)" />
                            </div>
                            <!-- File row (multiselect) -->
                            <div v-else class="flex items-center gap-2 w-full select-none -my-3 py-3">
                                <div class="flex items-center gap-2 grow min-w-0 cursor-pointer">
                                    <div class="shrink-0 size-7 rounded-sm overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <img
                                            v-if="meta.proxy && row?.blocked !== true && isMediaItem(row)"
                                            draggable="false"
                                            :src="thumbnailUrl(row)"
                                            class="size-full object-cover" />
                                        <i v-else class="material-symbols-outlined text-gray-400 dark:text-gray-600 text-base">description</i>
                                    </div>
                                    <span class="truncate">{{ value }}</span>
                                </div>
                                <Button
                                    icon="pencil"
                                    variant="ghost"
                                    size="xs"
                                    round
                                    :title="__('fairu::fieldtype.edit')"
                                    @click.stop="openEditor(row)" />
                            </div>
                        </template>
                        <template #prepended-row-actions="{ row }">
                            <!-- Folder actions -->
                            <DropdownItem
                                v-if="row.type === 'folder'"
                                :text="__('fairu::browser.open')"
                                icon="folder"
                                @click="selectFolder(row._isParent ? folder?.parent_id : row.id)" />
                            <!-- File actions -->
                            <template v-else-if="!isFolderMode">
                                <DropdownItem
                                    :text="__('fairu::browser.preview')"
                                    icon="eye"
                                    @click="setPreview(getFileIndex(row))" />
                                <DropdownItem
                                    :text="__('fairu::fieldtype.edit')"
                                    icon="pencil"
                                    @click="openEditor(row)" />
                                <DropdownItem
                                    :text="__('fairu::fieldtype.rename')"
                                    icon="rename"
                                    @click="openRename(row)" />
                                <DropdownItem
                                    :text="__('fairu::fieldtype.move')"
                                    icon="folder-open"
                                    @click="openMove(row)" />
                                <DropdownItem
                                    :text="__('fairu::browser.edit_in_fairu')"
                                    icon="external-link"
                                    :href="meta.file + '/' + row.id"
                                    target="_blank" />
                                <DropdownItem
                                    :text="__('fairu::fieldtype.delete')"
                                    icon="trash"
                                    variant="destructive"
                                    class="!text-red-600 dark:!text-red-400"
                                    @click="openDelete(row)" />
                            </template>
                        </template>
                    </ListingTable>
                </Panel>
                <div class="h-4" aria-hidden="true" />
            </Listing>

            <!-- TILES MODE (with its own toolbar) -->
            <template v-if="!loadingList && displayType === 'tiles'">
                <Panel class="relative !mb-0">
                    <!-- Toolbar overlaid at top-right -->
                    <div class="flex items-center justify-between gap-2 px-3 py-1.5">
                        <div class="flex gap-2 items-center">
                            <Button
                                v-if="multiselect && assets?.length > 0"
                                size="xs"
                                :variant="showSelection ? 'filled' : 'ghost'"
                                @click="toggleShowSelection">
                                {{ __('fairu::browser.only_selection') }} ({{ assets?.length }})
                            </Button>
                        </div>
                        <ToggleGroup
                            variant="ghost"
                            size="xs"
                            :model-value="displayType"
                            @update:model-value="displayType = $event">
                            <ToggleItem icon="layout-list" value="list" />
                            <ToggleItem icon="layout-grid" value="tiles" />
                        </ToggleGroup>
                    </div>
                <div
                    class="grid gap-4"
                    style="grid-template-columns: repeat(auto-fill, minmax(230px, 1fr))">
                    <Folder
                        custom
                        name="..."
                        @click="selectFolder(folder?.parent_id)"
                        v-if="folder"
                        displayType="tiles" />
                    <Folder
                        v-if="!showSelection"
                        v-for="(item, index) in folderItems"
                        :key="item.id + index"
                        :asset="item"
                        displayType="tiles"
                        @click="(asset) => selectFolder(asset.id)" />
                    <browser-list-item
                        v-for="(item, index) in showSelection
                            ? assets
                            : fileItems"
                        :key="item.id + index"
                        :asset="item"
                        :meta="meta"
                        :disabled="
                            (config.max_files &&
                                config.max_files > 0 &&
                                assets?.length >= config.max_files &&
                                !isSelected(item)) ||
                            (selectionType === 'folder' && item?.type !== 'folder')
                        "
                        :selected="isSelected(item)"
                        :multiselect="multiselect"
                        displayType="tiles"
                        @change="multiselect ? toggleItemSelection(item) : selectItem(item)"
                        @preview="setPreview(index)"
                        @edit="openEditor(item)"
                        @rename="openRename(item)"
                        @move="openMove(item)"
                        @delete="openDelete(item)" />
                </div>
                </Panel>
                <div class="h-4" aria-hidden="true" />
            </template>

            <!-- Preview gallery -->
            <FairuPreview
                v-if="previewImage"
                :items="fileItems"
                :startIndex="previewItem ?? 0"
                :meta="meta"
                :multiselect="multiselect"
                :isFolderMode="isFolderMode"
                :isSelectedFn="isSelected"
                @close="closePreview"
                @edit="editPreview"
                @toggle-select="handlePreviewToggle"
                @confirm-select="handlePreviewConfirm"
                @apply="handlePreviewApply" />
        </dropzone>

        <!-- Footer -->
        <template #footer-start>
            <Pagination
                v-if="folderContent && !showSelection"
                :resource-meta="folderContent"
                :per-page="perPage"
                :scroll-to-top="false"
                :show-per-page-selector="false"
                :show-totals="true"
                :show-page-links="true"
                @page-selected="handlePageSelected" />
        </template>
        <template #footer-end>
            <Button
                :text="__('fairu::browser.cancel')"
                @click="emitClose" />
            <Button
                variant="primary"
                :text="__('fairu::browser.select')"
                @click="sendSelection" />
        </template>
    </Stack>
</template>
