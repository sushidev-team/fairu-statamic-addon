<template>
    <div>
        <!-- <fairu-browser
            v-if="searchOpen"
            :multiselect="multiselect"
            :initial-assets="assets"
            :meta="meta"
            :config="config"
            @close="searchOpen = false"
            @selected="handleSelected" /> -->
        <Dropzone
            :enabled="canUpload"
            class="border rounded border-slate-400 fa-overflow-hidden fa-bg-slate-100 dark:fa-border-zinc-900 dark:fa-bg-dark-900 dark:fa-bg-zinc-800"
            @dropped="handleFileDrop">
            <div>
                <input
                    ref="upload"
                    class="hidden"
                    type="file"
                    @change="handleUpload" />
                <div
                    v-show="(multiselect || assets?.length < 1) && !uploading"
                    class="flex flex-wrap items-center p-3 gap-x-4">
                    <button
                        class="btn"
                        @click="openSearch()"
                        ><i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i>
                        {{ __('fairu::fieldtype.search') }}
                    </button>
                    <div>
                        <p class="flex-1 text-xs">
                            <button
                                type="button"
                                class="underline text-blue"
                                @click="openFile">
                                {{ __('fairu::fieldtype.upload_file') }}
                            </button>
                            <span class="fa-ml-1.5 fa-text-gray-500">{{
                                __('fairu::fieldtype.or_add_per_drag_and_drop')
                            }}</span>
                        </p>
                    </div>
                </div>

                <div
                    v-show="assets?.length > 0 || loading || uploading"
                    class="flex items-center gap-2 fa-cursor-pointer"
                    :class="
                        multiselect
                            ? 'p-2 fa-border-t fa-border-slate-300 fa-bg-white hover:fa-bg-white/70 dark:fa-border-zinc-700 dark:fa-bg-zinc-800 dark:hover:fa-bg-zinc-700/60'
                            : 'fa-p-2.5 hover:fa-bg-white/40 dark:fa-bg-zinc-800/40 dark:hover:fa-bg-zinc-700/60'
                    "
                    @click.prevent="openSearch">
                    <span v-if="uploading">{{ percentUploaded }}%</span>
                    <div
                        v-if="!loading && !uploading"
                        class="w-full">
                        <div
                            v-if="(config.max_files && config.max_files !== 1) || config.min_files"
                            class="text-xs fa-divide-x fa-divide-gray-200 fa-text-gray-400 dark:fa-divide-zinc-700 dark:fa-text-zinc-500"
                            ><span
                                v-if="config.min_files"
                                class="fa-pr-2"
                                >{{ __('fairu::fieldtype.rules.min') }}: {{ config.min_files }}</span
                            ><span
                                v-if="config.max_files"
                                class="fa-pl-2 first:fa-pl-0"
                                >{{ __('fairu::fieldtype.rules.max') }}: {{ config.max_files }}</span
                            ></div
                        >
                        <div
                            v-for="(item, index) in assets"
                            :key="item.id + index"
                            class="grid w-full min-w-0 gap-2 items-center fa-mt-2 fa-grid-cols-[auto,auto,1fr,auto] fa-border-t fa-border-zinc-200 fa-pt-2 first:fa-mt-0 first:fa-border-none first:fa-pt-0 dark:fa-border-zinc-700">
                            <div
                                :aria-label="__('fairu::fieldtype.asset.availability')"
                                class="mx-2 fa-size-2 fa-rounded-full"
                                :title="
                                    isAvailable(item)
                                        ? __('fairu::fieldtype.asset.item_available')
                                        : __('fairu::fieldtype.asset.item_unavailable')
                                "
                                :class="isAvailable(item) ? 'fa-bg-green-500' : 'bg-red-500'"></div>
                            <img
                                v-if="
                                    loading == false &&
                                    !metaItemsFetching.has(item.id) &&
                                    item?.mime?.match(/^(image|video)\//)
                                "
                                ref="fileImage"
                                :key="item.id + index + 'image'"
                                class="flex-none overflow-hidden rounded-md"
                                :class="multiselect ? 'fa-size-8' : 'fa-size-10'"
                                :src="meta.proxy + '/' + item?.id + '/thumbnail.webp?width=50&height=50'" />
                            <div class="flex w-full min-w-0 fa-content-center fa-items-center fa-justify-between">
                                <div
                                    class="min-w-0 text-xs truncate"
                                    v-html="item?.name"></div>
                                <div
                                    class="min-w-0 text-xs truncate fa-opacity-30"
                                    v-html="getSize(item)"></div>
                            </div>
                            <div class="flex items-center gap-1 justify-end">
                                <a
                                    :href="meta.file + '/' + item?.id"
                                    target="_blank"
                                    :title="__('fairu::fieldtype.open_in_fairu')"
                                    class="flex items-center text-xs cursor-pointer hover:text-blue !fa-text-gray-300 dark:!fa-text-zinc-500"
                                    @click.stop
                                    ><i class="text-lg material-symbols-outlined">open_in_new</i></a
                                >
                                <button
                                    class="flex items-center text-xs cursor-pointer !fa-text-gray-300 hover:!fa-text-gray-800 dark:!fa-text-zinc-500 dark:hover:!fa-text-zinc-100"
                                    title="__('fairu::fieldtype.delete')"
                                    @click.prevent.stop="clearAsset(item)"
                                    ><i class="text-lg material-symbols-outlined">delete</i></button
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dropzone>
    </div>
</template>

<script setup>
import { ref, useTemplateRef, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { Fieldtype } from '@statamic/cms';
import { fairuUpload } from '../../utils/fetches';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps({ ...Fieldtype.props, meta: Object, config: Object, container: String });
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

// Refs
const upload = useTemplateRef('upload');
const fileImage = useTemplateRef('fileImage');

// Reactive data
const assets = ref([]);
const searchOpen = ref(false);
const multiselect = ref(false);
const loading = ref(true);
const loadingList = ref(false);
const uploading = ref(false);
const percentUploaded = ref(null);
const metaItemsFetching = ref(new Set());

// Computed
const canBrowse = computed(() => {
    // Note: This needs to be adapted for Vue 3 as Fieldtype mixin is not available
    const hasPermission = true; // Placeholder - needs proper implementation
    if (!hasPermission) return false;
    return !false; // Placeholder for hasPendingDynamicFolder
});

const canUpload = computed(() => {
    // Note: This needs to be adapted for Vue 3 as Fieldtype mixin is not available
    const hasPermission = true; // Placeholder - needs proper implementation
    const allow = hasPermission && props.config.allow_uploads;
    return allow;
});

// Methods
const openSearch = () => {
    searchOpen.value = true;
};

const getExtension = (mime) => {
    const parts = mime.split('/');
    if (parts.length === 2) {
        return parts[1];
    }
    return 'n/a';
};

const getSize = (item) => {
    if (!item?.size) return null;
    return (item.size / 1024 / 1024).toFixed(2) + ' MB';
};

const isAvailable = (item) => {
    return item?.exists && !item?.locked;
};

const openFile = () => {
    if (upload.value) {
        upload.value.value = null;
        upload.value.click();
    }
};

const clearAsset = (item) => {
    assets.value = assets.value.filter((e) => e.id !== item.id);
    nextTick(() => {
        sendUpdate();
    });
};

const handleSelected = (selectedAssets) => {
    assets.value = multiselect.value ? selectedAssets : [selectedAssets];
    nextTick(() => {
        sendUpdate();
    });
};

const handleFileChange = (evt) => {
    const files = evt.target.files;
    handleUpload(files);
};

const handleFileDrop = (files) => {
    if (!files) return;
    handleUpload(files);
};

const handleUpload = (files) => {
    // $progress.start("upload" + this._uid); // Needs Vue 3 adaptation
    percentUploaded.value = 0;
    uploading.value = true;

    const successCallback = (result) => {
        searchOpen.value = false;
        uploading.value = false;
        // $progress.complete("upload" + this._uid); // Needs Vue 3 adaptation
        // $toast.success("Datei erfolgreich hochgeladen."); // Needs Vue 3 adaptation
        const resultIds = result?.data?.map((e) => e.id);
        resultIds.forEach((id) => {
            metaItemsFetching.value.add(id);
        });
        nextTick(async () => {
            const fetchedAssets = await loadMetaData(resultIds);
            if (multiselect.value) {
                if (fetchedAssets?.length > 0) {
                    const remainingSlots = Math.max(0, props.config.max_files - assets.value.length);

                    assets.value.push(...fetchedAssets.slice(0, remainingSlots));
                }
            } else {
                assets.value = fetchedAssets.slice(0, 1);
                sendUpdate();
            }
            resultIds.forEach((id) => {
                metaItemsFetching.value.delete(id);
            });
        });
    };

    const errorCallback = (err) => {
        uploading.value = false;
        // $progress.complete("upload" + this._uid); // Needs Vue 3 adaptation
        // $toast.error(err.response.data.message); // Needs Vue 3 adaptation
        if (upload.value) {
            upload.value.value = null;
        }
    };

    fairuUpload({
        files,
        folder: props.config.folder ?? null,
        onUploadProgressCallback: (progressEvent) => {
            percentUploaded.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        },
        successCallback,
        errorCallback,
    });
};

const sendUpdate = () => {
    emit(
        'update',
        assets.value?.map((e) => e.id),
    );
};

const loadMetaData = async (ids) => {
    if (!ids && !assets.value) {
        loading.value = false;
        return [];
    }

    const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);

    if (assetIds.length === 0) return [];

    loading.value = true;

    try {
        return axios
            .post('/fairu/files/list', { ids: assetIds })
            .then((result) => result.data)
            .catch((err) => {
                console.error(`Error fetching files:`, err);
                // Return placeholder objects with entry IDs when access is denied
                return assetIds.map((id) => ({
                    id: id,
                    name: `ID: ${id}`,
                    exists: false,
                    locked: true,
                }));
            });
    } catch (error) {
        console.error('Error in loadMetaData:', error);
        return [];
    } finally {
        loading.value = false;
    }
};

// Lifecycle
onMounted(async () => {
    multiselect.value = props.config.max_files !== 1;
    assets.value = await loadMetaData(props.value);
    console.log(props.config);
});

onBeforeUnmount(() => {
    // Cleanup if needed
});
</script>
