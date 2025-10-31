<template>
    <stack @closed="emitClose">
        <div class="grid h-full bg-white dark:bg-dark-800 fa-grid-rows-[auto,auto,1fr,auto,auto]">
            <section>
                <input
                    ref="upload"
                    class="hidden"
                    type="file"
                    @change="handleFileChange" />
                <div class="flex gap-2 p-2 bg-white dark:bg-dark-800 data-list-border justify-stretch">
                    <div
                        v-if="createFolderInputVisible"
                        class="flex flex-grow gap-1">
                        <input
                            ref="newfolder"
                            v-model="newFolderName"
                            class="h-8 input-text"
                            type="text"
                            :placeholder="__('fairu::browser.new_folder_name')" />
                        <button
                            class="flex items-center gap-1 btn btn-primary btn-sm"
                            @click="handleCreateFolder">
                            <span>{{ __('fairu::browser.create') }}</span>
                        </button>
                        <button
                            class="flex items-center gap-1 btn btn-sm"
                            @click="closeCreateFolder">
                            <span>{{ __('fairu::browser.cancel') }}</span>
                        </button>
                    </div>
                    <input
                        v-if="!createFolderInputVisible"
                        ref="search"
                        class="h-8 input-text"
                        type="text"
                        :placeholder="__('fairu::browser.search_in_folder')"
                        @input="handleSearchInput" />
                    <button
                        v-if="!createFolderInputVisible && selectionType !== 'folder' && canUpload"
                        type="button"
                        class="flex items-center gap-1 btn btn-sm"
                        @click="openFile(folder)">
                        <i class="text-gray-700 material-symbols-outlined">upload</i>
                        <span>{{ __('fairu::browser.upload') }}</span>
                    </button>
                    <button
                        v-if="!createFolderInputVisible && canUpload"
                        href="#"
                        class="flex items-center gap-1 btn btn-sm"
                        @click="openCreateFolder">
                        <i class="text-gray-700 material-symbols-outlined">create_new_folder</i>
                        <span>{{ __('fairu::browser.new_folder') }}</span>
                    </button>
                </div>
            </section>
            <section>
                <div
                    class="grid items-center w-full fa-min-h-10 fa-grid-cols-[1fr,auto] fa-justify-between fa-gap-4 fa-border-y fa-border-gray-200 fa-bg-gray-50 fa-px-3 fa-py-2 fa-text-gray-600 dark:fa-border-zinc-700 dark:fa-bg-zinc-800 dark:fa-text-zinc-400">
                    <div class="flex gap-2 fa-items-center">
                        <input-checkbox
                            v-if="multiselect"
                            :checked="
                                showSelection ||
                                folderContent?.data.every((file) => assets.map((e) => e.id).includes(file.id)) ||
                                (config.max_files && assets?.length >= config.max_files)
                            "
                            @change="toggleCurrentSelection" />
                        <button
                            v-if="assets?.length > 0"
                            class="px-2 py-1 text-xs border rounded fa-border-gray-200 disabled:fa-opacity-60 dark:fa-border-gray-700"
                            :class="showSelection ? 'text-white fa-bg-blue-500' : 'fa-bg-white dark:fa-bg-zinc-900'"
                            @click="toggleShowSelection"
                            >{{ __('fairu::browser.only_selection')
                            }}<span class="ml-1">({{ assets?.length }})</span></button
                        >
                        <button
                            v-if="assets?.length > 0"
                            class="flex items-center px-2 py-1 text-xs border rounded fa-border-gray-200 disabled:fa-opacity-60 dark:fa-border-gray-700"
                            @click="clearSelection"
                            ><i class="mr-1 text-sm text-gray-700 material-symbols-outlined">clear</i
                            >{{ __('fairu::browser.clear_selection') }}</button
                        >
                    </div>
                    <div>
                        <select
                            v-model="displayType"
                            class="px-2 py-1 text-sm bg-white"
                            ><option value="list">{{ __('fairu::browser.display_types.list') }}</option>
                            ><option value="tiles">{{ __('fairu::browser.display_types.tiles') }}</option>
                        </select>
                    </div>
                </div>
            </section>
            <dropzone
                :enabled="canUpload"
                class="relative overflow-y-auto size-full"
                @dropped="handleFileDrop">
                <div
                    v-if="loadingList"
                    class="grid items-center justify-center w-full h-full p-8">
                    Loading...
                </div>
                <div
                    v-if="!loadingList"
                    :class="displayType === 'tiles' ? 'grid p-3 fa-grid-cols-assets-big fa-gap-4' : 'px-2 group'">
                    <folder
                        v-if="folder"
                        custom
                        name="..."
                        :display-type="displayType"
                        @click="selectFolder(folder?.parent_id)" />

                    <folder
                        v-for="(item, index) in folderContent?.data?.filter((e) => e?.type === 'folder')"
                        v-if="!showSelection"
                        :key="item.id + index"
                        :asset="item"
                        :display-type="displayType"
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
                        :display-type="displayType"
                        @change="multiselect ? toggleItemSelection(item) : selectItem(item)"
                        @preview="setPreview(index)" />
                </div>
                <div
                    v-if="previewImage"
                    class="z-10 grid fa-fixed fa-inset-0 fa-size-full fa-grid-rows-[auto,1fr] fa-bg-white/95">
                    <div
                        class="fa-flex fa-h-min fa-max-h-screen fa-w-full fa-items-center fa-justify-end fa-gap-4 fa-p-4">
                        <div class="fa-text-lg fa-text-gray-900"
                            >{{ (previewItem ?? 0) + 1 }}/{{
                                folderContent?.data?.filter((e) => e.type !== 'folder')?.length
                            }}</div
                        >
                        <button
                            class="flex items-center"
                            @click="previewItem = null">
                            <i
                                class="text-3xl text-gray-900 material-symbols-outlined fa-pointer-events-none dark:!fa-text-gray-200 dark:hover:!fa-text-blue-500"
                                >close</i
                            >
                        </button>
                    </div>
                    <div class="fa-grid fa-h-full fa-min-h-0 fa-items-center fa-pb-10">
                        <div
                            class="bg-white rounded-lg overflow-hidden fa-mx-auto fa-grid fa-size-full fa-max-h-[70vh] fa-min-h-0 fa-max-w-screen-xl fa-grid-rows-[1fr,auto] fa-shadow-lg">
                            <div class="fa-relative fa-size-full fa-min-h-0">
                                <img
                                    v-if="
                                        meta.proxy &&
                                        previewImage?.blocked != true &&
                                        (previewImage?.mime?.startsWith('image/') ||
                                            previewImage?.mime?.startsWith('video/'))
                                    "
                                    draggable="false"
                                    :src="`${meta.proxy}/${previewImage.id}/thumbnail.webp?width=1280`"
                                    class="fa-size-full fa-object-contain"
                                    @click="
                                        multiselect ? toggleItemSelection(previewImage) : selectItem(previewImage)
                                    " />
                                <input-checkbox
                                    v-if="multiselect"
                                    :id="previewImage?.id"
                                    class="absolute fa-left-0 fa-top-0 fa-m-3"
                                    :checked="isSelected(previewImage)" />
                                <div class="grid fa-size-full fa-place-items-center">
                                    <div
                                        v-if="
                                            !previewImage?.mime?.startsWith('image/') &&
                                            !previewImage?.mime?.startsWith('video/')
                                        "
                                        class="fa-whitespace-pre-wrap fa-text-gray-600">
                                        <i
                                            class="material-symbols-outlined fa-pointer-events-none fa-text-[200px] fa-text-gray-900 dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500"
                                            >description</i
                                        >
                                    </div>
                                </div>
                                <button
                                    class="absolute fa-left-8 fa-top-1/2 fa-z-10 fa-grid fa-size-12 -fa-translate-y-1/2 fa-place-items-center fa-rounded-full fa-border fa-border-gray-500 fa-bg-white fa-shadow-sm"
                                    @click.stop.prevent="navigatePreview(-1)">
                                    <i
                                        class="text-lg text-gray-900 material-symbols-outlined fa-pointer-events-none dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500"
                                        >arrow_back</i
                                    >
                                </button>
                                <button
                                    class="absolute fa-right-8 fa-top-1/2 fa-z-10 fa-grid fa-size-12 -fa-translate-y-1/2 fa-place-items-center fa-rounded-full fa-border fa-border-gray-500 fa-bg-white fa-shadow-sm"
                                    @click.stop.prevent="navigatePreview(1)">
                                    <i
                                        class="text-lg text-gray-900 material-symbols-outlined fa-pointer-events-none dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500"
                                        >arrow_forward</i
                                    >
                                </button>
                            </div>
                            <div
                                class="flex items-center justify-between gap-2 grow fa-border-t fa-border-gray-100 fa-bg-gray-100/50 fa-px-6 fa-py-3 fa-text-base">
                                <div class="fa-font-normal">{{ previewImage.name }}</div>
                                <div class="fa-text-xs fa-uppercase fa-tracking-wider fa-text-gray-500">{{
                                    getExtension(previewImage.mime)
                                }}</div>
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
                        style="letter-spacing: 0.1rem"
                        >Powered by</span
                    >
                    <img
                        class="w-12 h-auto ml-1"
                        src="../../svg/fairu-logo.svg"
                        alt="Fairu Asset Service" /> </a
            ></div>
            <div
                class="flex flex-wrap justify-between gap-4 px-3 py-3 border-t border-gray-100 dark:border-dark-600 dark:bg-dark-900 fa-bg-slate-50">
                <div>
                    <div
                        v-if="!showSelection"
                        class="flex items-center justify-end gap-1 -mt-px">
                        <button
                            :disabled="page <= 1"
                            class="flex items-center gap-1 btn btn-sm"
                            @click.prevent="goToPage(1)">
                            <i class="dark:text-gray-300 material-symbols-outlined">first_page</i>
                        </button>
                        <button
                            :disabled="page <= 1"
                            class="flex items-center gap-1 btn btn-sm"
                            @click.prevent="previousPage">
                            {{ __('fairu::browser.previous') }}
                        </button>
                        <div class="px-2 text-sm">{{ page }} / {{ lastPage || 1 }}</div>
                        <button
                            :disabled="page >= lastPage"
                            class="flex items-center gap-1 btn btn-sm"
                            @click.prevent="nextPage">
                            {{ __('fairu::browser.next') }}
                        </button>
                        <button
                            :disabled="page >= lastPage"
                            class="flex items-center gap-1 btn btn-sm"
                            @click.prevent="goToPage(lastPage)">
                            <i class="dark:text-gray-300 material-symbols-outlined">last_page</i>
                        </button>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button
                        v-if="!createFolderInputVisible"
                        class="flex items-center gap-1 btn"
                        @click="close">
                        <span>{{ __('fairu::browser.cancel') }}</span>
                    </button>
                    <button
                        v-if="!createFolderInputVisible"
                        class="flex items-center gap-1 btn btn-primary"
                        @click="sendSelection">
                        <span>{{ __('fairu::browser.select') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </stack>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Dropzone from './Dropzone.vue';
import BrowserListItem from './browser/BrowserListItem.vue';
import InputCheckbox from './input/InputCheckbox.vue';
import { fairuLoadFolder, fairuUpload } from '../utils/fetches';
import axios from 'axios';

// Props
const props = defineProps({
    meta: null,
    config: null,
    initialAssets: [],
    selectionType: {
        type: String,
        default: 'files',
    },
    multiselect: Boolean,
});

// Emits
const emit = defineEmits(['close', 'selected']);

// Refs
const upload = ref(null);
const search = ref(null);
const newfolder = ref(null);

// Reactive data
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
const previewItem = ref(null);
const newFolderName = ref('');
const fetchingMetaData = ref(false);
let searchTimer = null;

// Computed
const url = computed(() => {
    return props.meta.proxy + '/' + props.asset?.id + '/thumbnail.webp?width=50&height=50';
});

const canUpload = computed(() => {
    // Note: This needs to be adapted for Vue 3 as Fieldtype mixin is not available
    const hasPermission = true; // Placeholder - needs proper implementation
    const allow = hasPermission && props.config.allow_uploads;
    return allow;
});

const previewImage = computed(() => {
    if (previewItem.value === null || previewItem.value === undefined) return null;
    return folderContent.value?.data?.filter((e) => e?.type !== 'folder')?.[previewItem.value];
});

// Methods
const emitClose = () => {
    emit('close');
};

const openFile = () => {
    if (upload.value) {
        upload.value.value = null;
        upload.value.click();
    }
};

const openCreateFolder = () => {
    createFolderInputVisible.value = true;
};

const closeCreateFolder = () => {
    createFolderInputVisible.value = false;
    newFolderName.value = null;
};

const selectFolder = (folderId) => {
    page.value = 1;
    loadFolder(null, folderId);
    search.value.value = null;
};

const selectItem = (asset) => {
    emit('selected', asset);
    nextTick(() => {
        emitClose();
    });
};

const sendSelection = () => {
    emit('selected', props.selectionType === 'folder' ? folder.value : assets.value);
    nextTick(() => {
        emitClose();
    });
};

const toggleItemSelection = (asset) => {
    if (assets.value.find((e) => e?.id === asset.id)) {
        assets.value = assets.value.filter((e) => e?.id !== asset.id);
    } else {
        assets.value.push(asset);
    }
    if (assets.value.length < 1) showSelection.value = false;
};

const toggleShowSelection = () => {
    showSelection.value = !showSelection.value;
};

const clearSelection = () => {
    assets.value = [];
    showSelection.value = false;
};

const toggleCurrentSelection = () => {
    if (showSelection.value) {
        clearSelection();
        return;
    }

    const folderFiles = folderContent.value?.data.filter((e) => e.type !== 'folder');
    if (
        folderFiles.every((file) => assets.value.map((e) => e.id).includes(file.id)) ||
        (props.config.max_files && assets.value?.length >= props.config.max_files)
    ) {
        assets.value = assets.value.filter((asset) => !folderFiles.map((file) => file.id).includes(asset.id));
    } else {
        const newItems = folderFiles.filter((file) => !assets.value.map((e) => e.id).includes(file.id));
        if (!newItems) return;

        const remainingSlots = Math.max(0, props.config.max_files - assets.value.length);

        assets.value.push(...newItems.slice(0, remainingSlots));
    }
};

const setPreview = (itemIndex) => {
    previewItem.value = itemIndex;
};

const navigatePreview = (diff) => {
    previewItem.value = Math.min(
        Math.max(0, previewItem.value + diff),
        (folderContent.value?.data?.filter((e) => e.type !== 'folder')?.length ?? 1) - 1,
    );
};

const isSelected = (asset) => {
    return assets.value?.findIndex((e) => e?.id === asset.id) > -1;
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
    const errorCallback = (err) => {
        loading.value = false;
        // $progress.complete("upload" + this._uid); // Needs Vue 3 adaptation
        // $toast.error(err.response.data.message); // Needs Vue 3 adaptation
        if (upload.value) {
            upload.value.value = null;
        }
    };

    const successCallback = (result) => {
        // $progress.complete("upload" + this._uid); // Needs Vue 3 adaptation
        // $toast.success(__("fairu::browser.files_uploaded_successfully")); // Needs Vue 3 adaptation
        fetchingMetaData.value = true;
        nextTick(async () => {
            const fetchedAssets = await loadMetaData(result?.data?.map((e) => e.id));
            if (props.multiselect) {
                if (fetchedAssets?.length > 0) {
                    const remainingSlots = Math.max(0, props.config.max_files - assets.value.length);

                    assets.value.push(...fetchedAssets.slice(0, remainingSlots));
                }
                await loadFolder();
            } else {
                assets.value = fetchedAssets.slice(0, 1);
                selectItem(assets.value[0]);
            }
            fetchingMetaData.value = false;
        });
    };
    // $progress.start("upload" + this._uid); // Needs Vue 3 adaptation
    percentUploaded.value = 0;
    loading.value = true;

    fairuUpload({
        files,
        folder: folder.value?.id ?? null,
        onUploadProgressCallback: (progressEvent) => {
            percentUploaded.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        },
        successCallback,
        errorCallback,
    });
};

const handleCreateFolder = () => {
    loading.value = true;
    axios
        .post('/fairu/folders/create', {
            name: newFolderName.value,
            folder: folder.value?.id ?? null,
        })
        .then(async (result) => {
            nextTick(() => {
                closeCreateFolder();
                loadFolder();
            });
        })
        .catch((error) => {
            // $toast.error(error.response.data.message); // Needs Vue 3 adaptation
            loading.value = false;
        });
};

const handleSearchInput = (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadFolder(e.target.value);
    }, 250);
};

const nextPage = () => {
    page.value = page.value + 1;
    loadFolder(search.value.value);
};

const previousPage = () => {
    page.value = page.value - 1;
    loadFolder(search.value.value);
};

const goToPage = (pageValue) => {
    page.value = pageValue;
    loadFolder(search.value.value);
};

const loadFolder = async (search, folderId) => {
    loadingList.value = true;
    let retriesAvailable = 1;

    return await fairuLoadFolder({
        page: page.value,
        folder: folderId !== undefined ? folderId : folder.value?.id,
        search: search ?? null,
        successCallback: (result) => {
            folder.value = result.data.entry;
            folderContent.value = result.data.entries;
            lastPage.value = result.data.entries?.last_page;

            loadingList.value = false;
            return result.data.entry;
        },
        errorCallback: () => {
            if (folderId && retriesAvailable >= 0) {
                retriesAvailable -= 1;
                // $toast.error("There was an error accessing the folder."); // Needs Vue 3 adaptation
                loadFolder(search);
            }
            folderContent.value = null;
            folder.value = null;
            loadingList.value = false;
        },
    });
};

const loadMetaData = async (ids) => {
    if (!ids && !assets.value) return [];

    // Ensure ids is always an array
    const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);

    if (assetIds.length === 0) return [];

    loading.value = true;

    try {
        return axios
            .post('/fairu/files/list', { ids: assetIds })
            .then((result) => result.data)
            .catch((err) => {
                console.error(
                    `Error fetching files:`, // __("fairu::browser.errors.error_fetching_files") + ":",
                    err,
                );
                return null;
            });
    } catch (error) {
        console.error(
            `Error fetching files:`, // __("fairu::browser.errors.error_fetching_files") + ":",
            error,
        );
        return [];
    } finally {
        loading.value = false;
    }
};

const getExtension = (mime) => {
    const parts = mime.split('/');
    if (parts.length === 2) {
        return parts[1];
    }
    return 'n/a';
};

// Lifecycle
onMounted(async () => {
    displayType.value = props.config.display_type;
    assets.value =
        props.config.max_files === 1 ? [] : [...(props.initialAssets?.length > 0 ? props.initialAssets : [])];
    try {
        await loadFolder(null, props.config.folder);
    } catch (error) {
        // $toast.error(__("fairu::browser.errors.error_loading_folder")); // Needs Vue 3 adaptation
        await loadFolder(null, null);
    }
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    if (search.value) {
        search.value.removeEventListener('input', handleSearchInput);
    }
});
</script>
