<script setup>
import { ref, computed, nextTick, onMounted, useId, getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { toast, progress, config } from '@statamic/cms/api';
import { Button } from '@statamic/cms/ui';
import FairuBrowser from '../FairuBrowser.vue';
import FairuAssetEditor from '../FairuAssetEditor.vue';
import Dropzone from '../Dropzone.vue';
import { fairuUpload, fairuLoadFilesMeta } from '../../utils/fetches';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const componentId = useId();
const uploadInput = ref(null);

const assets = ref(null);
const searchOpen = ref(false);
const loading = ref(true);
const uploading = ref(false);
const percentUploaded = ref(null);
const metaItemsFetching = ref(new Set());
const editingAssetId = ref(null);
const browserStartFolder = ref(null);

const multiselect = computed(() => props.config.max_files !== 1);

const canUpload = computed(() => {
    const user = config.get('user');
    const hasPermission = user?.super || (user?.permissions || []).includes('configure asset containers');
    return hasPermission && props.config.allow_uploads;
});

function openSearch() {
    browserStartFolder.value = null;
    if (!multiselect.value && assets.value?.length === 1) {
        const current = assets.value[0];
        browserStartFolder.value = current?.folder_id || current?.parent_id || null;
    }
    searchOpen.value = true;
}

function openEditor(item) {
    if (!item?.id) return;
    editingAssetId.value = item.id;
}

function closeEditor() {
    editingAssetId.value = null;
}

async function handleAssetSaved(updated) {
    if (!updated?.id) return;
    const refreshed = await loadMetaData([updated.id]);
    const refreshedAsset = refreshed?.[0];
    if (!refreshedAsset || !assets.value) return;
    assets.value = assets.value.map((e) =>
        e?.id === refreshedAsset.id ? { ...e, ...refreshedAsset } : e,
    );
}

function handleAssetRenamed({ id, name }) {
    if (!assets.value) return;
    assets.value = assets.value.map((e) => (e?.id === id ? { ...e, name } : e));
}

function handleAssetMoved({ id }) {
    if (!assets.value) return;
    assets.value = assets.value.map((e) => (e?.id === id ? { ...e, exists: true } : e));
}

function handleAssetDeleted({ id }) {
    if (!assets.value) return;
    assets.value = assets.value.filter((e) => e?.id !== id);
    sendUpdate();
}

function getSize(item) {
    if (!item?.size) return null;
    return (item.size / 1024 / 1024).toFixed(2) + ' MB';
}

function isAvailable(item) {
    return item?.exists && !item?.locked;
}

function openFile() {
    uploadInput.value.value = null;
    uploadInput.value.click();
}

function clearAsset(item) {
    assets.value = assets.value.filter((e) => e.id !== item.id);
    nextTick(() => sendUpdate());
}

function handleSelected(selected) {
    assets.value = multiselect.value ? selected : [selected];
    nextTick(() => sendUpdate());
}

function handleFileDrop(files) {
    if (!files) return;
    handleUpload(files);
}

function handleUpload(files) {
    progress.start('upload' + componentId);
    percentUploaded.value = 0;
    uploading.value = true;

    fairuUpload({
        files,
        folder: props.config.folder ?? null,
        onUploadProgressCallback: (progressEvent) => {
            percentUploaded.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        },
        successCallback: (result) => {
            searchOpen.value = false;
            uploading.value = false;
            progress.complete('upload' + componentId);
            toast.success(__('fairu::fieldtype.upload_success'));

            const newIds = result?.data?.map((e) => e.id) || [];
            metaItemsFetching.value = new Set([...metaItemsFetching.value, ...newIds]);

            nextTick(async () => {
                const fetchedAssets = await loadMetaData(newIds);
                if (multiselect.value) {
                    if (fetchedAssets?.length > 0) {
                        const maxFiles = Number.isFinite(props.config.max_files) ? props.config.max_files : Infinity;
                        const remainingSlots = Math.max(0, maxFiles - assets.value.length);
                        assets.value.push(...fetchedAssets.slice(0, remainingSlots));
                    }
                } else {
                    assets.value = fetchedAssets.slice(0, 1);
                    sendUpdate();
                }
                newIds.forEach((id) => metaItemsFetching.value.delete(id));
            });
        },
        errorCallback: (err) => {
            uploading.value = false;
            progress.complete('upload' + componentId);
            toast.error(err?.response?.data?.message || __('fairu::fieldtype.upload_error'));
            uploadInput.value.value = null;
        },
    });
}

function sendUpdate() {
    update(assets.value?.map((e) => e.id));
}

async function loadMetaData(ids) {
    if (!ids && !assets.value) {
        loading.value = false;
        return [];
    }

    const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);
    if (assetIds.length === 0) return [];

    loading.value = true;

    try {
        return await fairuLoadFilesMeta(assetIds);
    } catch (err) {
        console.error('Error fetching files:', err);
        return assetIds.map((id) => ({
            id,
            name: `ID: ${id}`,
            exists: false,
            locked: true,
        }));
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    assets.value = await loadMetaData(props.value);
});
</script>

<template>
    <div class="relative w-full bg-gray-50 dark:bg-transparent rounded-xl">
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="multiselect"
            :initialAssets="assets"
            :meta="meta"
            :config="props.config"
            :canUpload="canUpload"
            :startFolder="browserStartFolder" />
        <fairu-asset-editor
            v-if="editingAssetId"
            :assetId="editingAssetId"
            :meta="meta"
            @close="closeEditor"
            @saved="handleAssetSaved"
            @renamed="handleAssetRenamed"
            @moved="handleAssetMoved"
            @deleted="handleAssetDeleted" />
        <dropzone
            :enabled="canUpload"
            @dropped="handleFileDrop">
            <!-- Picker area -->
            <div
                v-if="(multiselect || assets?.length < 1) && !uploading"
                class="p-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-850 rounded-xl flex flex-row items-center gap-3"
                :class="{ 'rounded-b-none': assets?.length > 0 }">
                <input
                    class="hidden"
                    type="file"
                    ref="uploadInput"
                    @change="(e) => handleUpload(e.target.files)" />
                <Button
                    icon="folder-open"
                    :text="__('fairu::fieldtype.search')"
                    @click="openSearch()" />
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <button
                        type="button"
                        class="text-blue-600 underline hover:text-blue-800 dark:text-blue-400"
                        @click="openFile"
                        v-if="canUpload">{{ __('fairu::fieldtype.upload_file') }}</button>
                    <span class="ml-1 text-gray-500 dark:text-gray-400">{{
                        __('fairu::fieldtype.or_add_per_drag_and_drop')
                    }}</span>
                </div>
            </div>

            <!-- Loading / Uploading state -->
            <div
                v-if="(loading || uploading) && !(assets?.length > 0)"
                class="flex items-center gap-2 p-3 border border-gray-300 dark:border-gray-700 rounded-xl"
                :class="{ 'border-t-0 rounded-t-none': multiselect || assets?.length < 1 }">
                <svg class="animate-spin size-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span v-if="uploading" class="text-sm text-gray-600">{{ percentUploaded }}%</span>
            </div>

            <!-- Asset list -->
            <div
                v-if="assets?.length > 0 && !loading"
                class="relative overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700"
                :class="{ 'border-t-0 rounded-t-none': multiselect || assets?.length < 1 }">
                <!-- Min/Max info -->
                <div
                    class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900"
                    v-if="(props.config.max_files && props.config.max_files !== 1) || props.config.min_files">
                    <span v-if="props.config.min_files" class="mr-3">{{ __('fairu::fieldtype.rules.min') }}: {{ props.config.min_files }}</span>
                    <span v-if="props.config.max_files">{{ __('fairu::fieldtype.rules.max') }}: {{ props.config.max_files }}</span>
                </div>
                <!-- Asset rows -->
                <div
                    v-for="(item, index) in assets"
                    :key="item.id + index"
                    class="group relative flex items-center gap-2 sm:gap-3 p-3 bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-900 border-b dark:border-gray-600 last:border-b-0 cursor-pointer"
                    @click.prevent="openSearch">
                    <!-- Availability dot -->
                    <div
                        :aria-label="__('fairu::fieldtype.asset.availability')"
                        class="shrink-0 size-2 rounded-full"
                        :title="
                            isAvailable(item)
                                ? __('fairu::fieldtype.asset.item_available')
                                : __('fairu::fieldtype.asset.item_unavailable')
                        "
                        :class="isAvailable(item) ? 'bg-green-500' : 'bg-red-500'"></div>
                    <!-- Thumbnail -->
                    <img
                        v-if="
                            !loading &&
                            !metaItemsFetching.has(item.id) &&
                            item?.mime?.match(/^(image|video)\//)
                        "
                        :key="item.id + index + 'image'"
                        class="shrink-0 size-7 rounded-sm object-cover"
                        :src="meta.proxy + '/' + item?.id + '/thumbnail.webp?width=50&height=50'" />
                    <svg v-if="metaItemsFetching.has(item.id)" class="animate-spin shrink-0 size-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <!-- Name -->
                    <span class="truncate text-sm text-gray-600 dark:text-gray-400 grow min-w-0">{{ item?.name }}</span>
                    <!-- Actions (right-aligned, gradient fade) -->
                    <div class="flex shrink-0 items-center gap-1 bg-gradient-to-r from-transparent to-white dark:to-gray-900 pl-4">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ getSize(item) }}</span>
                        <Button
                            icon="pencil"
                            variant="ghost"
                            size="xs"
                            round
                            :title="__('fairu::fieldtype.edit')"
                            @click.prevent.stop="openEditor(item)" />
                        <Button
                            as="a"
                            :href="meta.file + '/' + item?.id"
                            target="_blank"
                            icon="external-link"
                            variant="ghost"
                            size="xs"
                            round
                            :title="__('fairu::fieldtype.open_in_fairu')"
                            @click.stop />
                        <Button
                            icon="x"
                            variant="ghost"
                            size="xs"
                            round
                            :title="__('fairu::fieldtype.delete')"
                            @click.prevent.stop="clearAsset(item)" />
                    </div>
                </div>
            </div>
        </dropzone>
    </div>
</template>
