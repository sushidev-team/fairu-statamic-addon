<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, getCurrentInstance } from 'vue';
import { toast, progress } from '@statamic/cms/api';
import { Stack, Button, Input, Checkbox, Select } from '@statamic/cms/ui';
import Dropzone from './Dropzone.vue';
import BrowserListItem from './browser/BrowserListItem.vue';
import Folder from './browser/Folder.vue';
import { fairuLoadFolder, fairuUpload, fairuCreateFolder, fairuLoadFilesMeta } from '../utils/fetches';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

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
});

const emit = defineEmits(['close', 'selected']);

const uploadInput = ref(null);
const searchQuery = ref('');

const assets = ref([]);
const loading = ref(false);
const folder = ref(null);
const page = ref(1);
const displayType = ref('list');
const lastPage = ref(1);
const loadingList = ref(false);
const showSelection = ref(false);
const percentUploaded = ref(0);
const folderContent = ref(null);
const createFolderInputVisible = ref(false);
const newFolderName = ref('');
const previewItem = ref(null);

const displayTypeOptions = [
    { value: 'list', label: __('fairu::browser.display_types.list') },
    { value: 'tiles', label: __('fairu::browser.display_types.tiles') },
];

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
    emit(
        'selected',
        props.selectionType === 'folder' ? folder.value : assets.value,
    );
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
        const remainingSlots = Math.max(0, props.config.max_files - assets.value.length);
        assets.value.push(...newItems.slice(0, remainingSlots));
    }
}

function setPreview(itemIndex) {
    previewItem.value = itemIndex;
}

function navigatePreview(diff) {
    const filesOnly = folderContent.value?.data?.filter((e) => e.type !== 'folder') || [];
    previewItem.value = Math.min(Math.max(0, previewItem.value + diff), filesOnly.length - 1);
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
                    const remainingSlots = Math.max(0, props.config.max_files - assets.value.length);
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

function nextPage() {
    page.value++;
    loadFolderContent(searchQuery.value);
}

function previousPage() {
    page.value--;
    loadFolderContent(searchQuery.value);
}

function goToPage(p) {
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
            lastPage.value = result.data.entries?.last_page;
            loadingList.value = false;
        },
        errorCallback: () => {
            if (folderId && retriesAvailable >= 0) {
                retriesAvailable -= 1;
                toast.error(__('fairu::browser.errors.error_accessing_folder'));
                loadFolderContent(search);
            }
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

const selectAllChecked = computed(() =>
    showSelection.value ||
    folderContent?.value?.data?.every((file) => assets.value.map((e) => e.id).includes(file.id)) ||
    (props.config.max_files && assets.value?.length >= props.config.max_files)
);

const previewImage = computed(() => {
    if (previewItem.value === null || previewItem.value === undefined) return null;
    return folderContent.value?.data?.filter((e) => e?.type !== 'folder')?.[previewItem.value];
});

onMounted(async () => {
    displayType.value = props.config.display_type || 'list';
    assets.value =
        props.config.max_files === 1
            ? []
            : [...(props.initialAssets?.length > 0 ? props.initialAssets : [])];
    try {
        await loadFolderContent(null, props.config.folder);
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
    <Stack open @closed="emitClose" inset :show-close-button="false">
        <div class="grid h-full bg-white dark:bg-dark-800" style="grid-template-rows: auto auto 1fr auto auto">
            <section>
                <input
                    class="hidden"
                    type="file"
                    ref="uploadInput"
                    @change="handleFileChange" />
                <div class="flex gap-2 p-2 bg-white dark:bg-dark-800 border-b border-gray-200 dark:border-zinc-700 justify-stretch">
                    <div
                        v-if="createFolderInputVisible"
                        class="flex flex-grow gap-1">
                        <Input
                            size="sm"
                            :placeholder="__('fairu::browser.new_folder_name')"
                            :model-value="newFolderName"
                            @update:model-value="newFolderName = $event" />
                        <Button
                            variant="primary"
                            size="sm"
                            :text="__('fairu::browser.create')"
                            @click="handleCreateFolder" />
                        <Button
                            size="sm"
                            :text="__('fairu::browser.cancel')"
                            @click="closeCreateFolder" />
                    </div>
                    <Input
                        v-if="!createFolderInputVisible"
                        size="sm"
                        icon="search"
                        :placeholder="__('fairu::browser.search_in_folder')"
                        :model-value="searchQuery"
                        @update:model-value="searchQuery = $event; handleSearchInput($event)" />
                    <Button
                        v-if="!createFolderInputVisible && selectionType !== 'folder' && canUpload"
                        size="sm"
                        icon="upload"
                        :text="__('fairu::browser.upload')"
                        @click="openFile()" />
                    <Button
                        v-if="!createFolderInputVisible && canUpload"
                        size="sm"
                        icon="create-new-folder"
                        :text="__('fairu::browser.new_folder')"
                        @click="openCreateFolder" />
                </div>
            </section>
            <section>
                <div class="flex items-center w-full min-h-10 justify-between gap-4 border-b border-gray-200 bg-gray-50 px-3 py-2 text-gray-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    <div class="flex gap-2 items-center">
                        <Checkbox
                            v-if="multiselect"
                            solo
                            size="sm"
                            :model-value="selectAllChecked"
                            @update:model-value="toggleCurrentSelection" />
                        <Button
                            v-if="assets?.length > 0"
                            size="2xs"
                            :variant="showSelection ? 'primary' : 'default'"
                            @click="toggleShowSelection">
                            {{ __('fairu::browser.only_selection') }} ({{ assets?.length }})
                        </Button>
                        <Button
                            v-if="assets?.length > 0"
                            size="2xs"
                            icon="x"
                            :text="__('fairu::browser.clear_selection')"
                            @click="clearSelection" />
                    </div>
                    <Select
                        size="xs"
                        :options="displayTypeOptions"
                        :model-value="displayType"
                        @update:model-value="displayType = $event" />
                </div>
            </section>
            <dropzone
                :enabled="canUpload"
                @dropped="handleFileDrop"
                class="relative overflow-y-auto size-full">
                <div
                    v-if="loadingList"
                    class="grid items-center justify-center w-full h-full p-8">
                    <svg class="animate-spin size-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </div>
                <div
                    v-if="!loadingList"
                    :class="displayType === 'tiles' ? 'grid p-3 gap-4' : 'px-2 group'"
                    :style="displayType === 'tiles' ? 'grid-template-columns: repeat(auto-fill, minmax(230px, 1fr))' : ''">
                    <Folder
                        custom
                        name="..."
                        @click="selectFolder(folder?.parent_id)"
                        v-if="folder"
                        :displayType="displayType" />

                    <Folder
                        v-if="!showSelection"
                        v-for="(item, index) in folderContent?.data?.filter((e) => e?.type === 'folder')"
                        :key="item.id + index"
                        :asset="item"
                        :displayType="displayType"
                        @click="(asset) => selectFolder(asset.id)" />
                    <browser-list-item
                        v-for="(item, index) in showSelection
                            ? assets
                            : folderContent?.data?.filter((e) => e?.type !== 'folder')"
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
                        :displayType="displayType"
                        @change="multiselect ? toggleItemSelection(item) : selectItem(item)"
                        @preview="setPreview(index)" />
                </div>
                <div
                    class="z-10 grid fixed inset-0 size-full bg-white/95"
                    style="grid-template-rows: auto 1fr"
                    v-if="previewImage">
                    <div class="flex h-min max-h-screen w-full items-center justify-end gap-4 p-4">
                        <div class="text-lg text-gray-900">
                            {{ (previewItem ?? 0) + 1 }}/{{ folderContent?.data?.filter((e) => e.type !== 'folder')?.length }}
                        </div>
                        <Button
                            icon="x"
                            variant="ghost"
                            @click="previewItem = null" />
                    </div>
                    <div class="grid h-full min-h-0 items-center pb-10">
                        <div class="bg-white rounded-lg overflow-hidden mx-auto grid size-full min-h-0 max-w-screen-xl shadow-lg" style="max-height: 70vh; grid-template-rows: 1fr auto">
                            <div class="relative size-full min-h-0">
                                <img
                                    v-if="
                                        meta.proxy &&
                                        previewImage?.blocked != true &&
                                        (previewImage?.mime?.startsWith('image/') ||
                                            previewImage?.mime?.startsWith('video/'))
                                    "
                                    draggable="false"
                                    :src="`${meta.proxy}/${previewImage.id}/thumbnail.webp?width=1280`"
                                    @click="multiselect ? toggleItemSelection(previewImage) : selectItem(previewImage)"
                                    class="size-full object-contain" />
                                <Checkbox
                                    v-if="multiselect"
                                    solo
                                    class="absolute left-0 top-0 m-3"
                                    :model-value="isSelected(previewImage)"
                                    @update:model-value="toggleItemSelection(previewImage)" />
                                <div class="grid size-full place-items-center">
                                    <div
                                        class="whitespace-pre-wrap text-gray-600"
                                        v-if="
                                            !previewImage?.mime?.startsWith('image/') &&
                                            !previewImage?.mime?.startsWith('video/')
                                        ">
                                        <i class="material-symbols-outlined pointer-events-none text-gray-900 dark:!text-gray-600" style="font-size: 200px">description</i>
                                    </div>
                                </div>
                                <Button
                                    class="absolute left-8 top-1/2 z-10 -translate-y-1/2"
                                    icon="chevron-left"
                                    round
                                    @click.stop.prevent="navigatePreview(-1)" />
                                <Button
                                    class="absolute right-8 top-1/2 z-10 -translate-y-1/2"
                                    icon="chevron-right"
                                    round
                                    @click.stop.prevent="navigatePreview(1)" />
                            </div>
                            <div class="flex items-center justify-between gap-2 grow border-t border-gray-100 bg-gray-100/50 px-6 py-3 text-base">
                                <div class="font-normal">{{ previewImage.name }}</div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">{{ getExtension(previewImage.mime) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </dropzone>
            <div>
                <a
                    class="flex items-center gap-1 px-3 py-1.5 text-2xs"
                    href="https://fairu.app"
                    style="color: #666">
                    <span
                        class="uppercase"
                        style="letter-spacing: 0.1rem">
                        Powered by
                    </span>
                    <img
                        class="w-12 h-auto ml-1"
                        src="../../svg/fairu-logo.svg"
                        alt="Fairu Asset Service" />
                </a>
            </div>
            <div class="flex flex-wrap justify-between gap-4 px-3 py-3 border-t border-gray-100 dark:border-dark-600 dark:bg-dark-900 bg-slate-50">
                <div>
                    <div
                        class="flex items-center justify-end gap-1"
                        v-if="!showSelection">
                        <Button
                            size="sm"
                            icon="chevron-double-left"
                            icon-only
                            :disabled="page <= 1"
                            @click.prevent="goToPage(1)" />
                        <Button
                            size="sm"
                            :text="__('fairu::browser.previous')"
                            :disabled="page <= 1"
                            @click.prevent="previousPage" />
                        <div class="px-2 text-sm">{{ page }} / {{ lastPage || 1 }}</div>
                        <Button
                            size="sm"
                            :text="__('fairu::browser.next')"
                            :disabled="page >= lastPage"
                            @click.prevent="nextPage" />
                        <Button
                            size="sm"
                            icon="chevron-double-right"
                            icon-only
                            :disabled="page >= lastPage"
                            @click.prevent="goToPage(lastPage)" />
                    </div>
                </div>
                <div class="flex gap-3">
                    <Button
                        v-if="!createFolderInputVisible"
                        :text="__('fairu::browser.cancel')"
                        @click="emitClose" />
                    <Button
                        v-if="!createFolderInputVisible"
                        variant="primary"
                        :text="__('fairu::browser.select')"
                        @click="sendSelection" />
                </div>
            </div>
        </div>
    </Stack>
</template>
