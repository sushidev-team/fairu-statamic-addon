<template>
    <stack @closed="emitClose">
        <div
            slot-scope="{ close }"
            class="grid h-full bg-white dark:bg-dark-800 fa-grid-rows-[auto,auto,1fr,auto,auto]">
            <section>
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleFileChange" />
                <div class="flex gap-2 p-2 bg-white dark:bg-dark-800 data-list-border justify-stretch">
                    <div
                        v-if="createFolderInputVisible"
                        class="flex flex-grow gap-1">
                        <input
                            class="h-8 input-text"
                            type="text"
                            ref="newfolder"
                            :placeholder="__('fairu::browser.new_folder_name')"
                            v-model="newFolderName" />
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
                        class="h-8 input-text"
                        type="text"
                        ref="search"
                        :placeholder="__('fairu::browser.search_in_folder')"
                        @input="handleSearchInput" />
                    <button
                        type="button"
                        class="flex items-center gap-1 btn btn-sm"
                        @click="openFile(folder)"
                        v-if="!createFolderInputVisible && selectionType !== 'folder' && canUpload">
                        <i class="text-gray-700 material-symbols-outlined">upload</i>
                        <span>{{ __('fairu::browser.upload') }}</span>
                    </button>
                    <button
                        href="#"
                        class="flex items-center gap-1 btn btn-sm"
                        @click="openCreateFolder"
                        v-if="!createFolderInputVisible">
                        <i class="text-gray-700 material-symbols-outlined">create_new_folder</i>
                        <span>{{ __('fairu::browser.new_folder') }}</span>
                    </button>
                </div>
            </section>
            <section>
                <div
                    class="flex items-center w-full gap-2 fa-border-y fa-border-gray-200 fa-bg-gray-50 fa-px-3 fa-py-2 fa-text-gray-600 dark:fa-border-zinc-700 dark:fa-bg-zinc-800 dark:fa-text-zinc-400"
                    v-if="multiselect && assets?.length > 0">
                    <input-checkbox
                        @change="toggleCurrentSelection"
                        :checked="
                            showSelection ||
                            folderContent?.data.every((file) => assets.map((e) => e.id).includes(file.id)) ||
                            (config.max_files && assets?.length >= config.max_files)
                        "
                        :disabled="assets.length < 1" />
                    <button
                        class="px-2 py-1 text-xs border rounded fa-border-gray-200 dark:fa-border-gray-700"
                        :class="showSelection ? 'text-white fa-bg-blue-500' : 'fa-bg-white dark:fa-bg-zinc-900'"
                        @click="toggleShowSelection"
                        v-if="assets?.length > 0"
                        >{{ __('fairu::browser.only_selection')
                        }}<span class="ml-1">({{ assets?.length }})</span></button
                    >
                    <button
                        class="flex items-center px-2 py-1 text-xs border rounded fa-border-gray-200 dark:fa-border-gray-700"
                        @click="clearSelection"
                        v-if="assets?.length > 0"
                        ><i class="mr-1 text-sm text-gray-700 material-symbols-outlined">clear</i
                        >{{ __('fairu::browser.clear_selection') }}</button
                    >
                </div>
            </section>
            <dropzone
                :enabled="canUpload"
                @dropped="handleFileDrop"
                class="relative overflow-y-auto size-full">
                <div
                    v-if="loadingList"
                    class="grid items-center justify-center w-full h-full p-8">
                    <ring-loader
                        color="#4a4a4a"
                        class="w-5 h-5"
                        size="24"
                        v-if="loadingList" />
                </div>
                <div v-show="!loadingList">
                    <div
                        v-if="folder"
                        class="px-2 group last:fa-border-b-none fa-border-b fa-border-slate-100 hover:fa-bg-gray-50 dark:fa-border-zinc-700 dark:hover:fa-bg-zinc-700">
                        <a
                            href="#"
                            class="flex items-center gap-1 px-2 py-1 fa-min-h-12"
                            @click="selectFolder(folder?.parent_id)">
                            <i class="text-gray-700 material-symbols-outlined fa-px-1 fa-text-2xl">folder</i> ...
                        </a>
                    </div>
                    <div
                        class="px-2 group last:fa-border-b-none fa-border-b fa-border-slate-100 hover:fa-bg-gray-50 dark:fa-border-zinc-700 dark:hover:fa-bg-zinc-700"
                        v-for="(item, index) in showSelection ? assets : folderContent?.data"
                        :key="item.id + index">
                        <button
                            v-if="item.type === 'folder'"
                            class="flex items-center w-full gap-1 px-2 py-1 text-sm fa-min-h-12"
                            @click="selectFolder(item.id)">
                            <i class="text-gray-700 material-symbols-outlined fa-px-1 fa-text-2xl">folder</i>
                            {{ item.name }}
                        </button>
                        <browser-list-item
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
                            @change="multiselect ? toggleItemSelection(item) : selectItem(item)" />
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
                        class="flex items-center justify-end gap-1 -mt-px"
                        v-if="!showSelection">
                        <button
                            :disabled="page <= 1"
                            @click.prevent="goToPage(1)"
                            class="flex items-center gap-1 btn btn-sm">
                            <i class="dark:text-gray-300 material-symbols-outlined">first_page</i>
                        </button>
                        <button
                            :disabled="page <= 1"
                            @click.prevent="previousPage"
                            class="flex items-center gap-1 btn btn-sm">
                            {{ __('fairu::browser.previous') }}
                        </button>
                        <div class="px-2 text-sm">{{ page }} / {{ lastPage || 1 }}</div>
                        <button
                            :disabled="page >= lastPage"
                            @click.prevent="nextPage"
                            class="flex items-center gap-1 btn btn-sm">
                            {{ __('fairu::browser.next') }}
                        </button>
                        <button
                            :disabled="page >= lastPage"
                            @click.prevent="goToPage(lastPage)"
                            class="flex items-center gap-1 btn btn-sm">
                            <i class="dark:text-gray-300 material-symbols-outlined">last_page</i>
                        </button>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button
                        class="flex items-center gap-1 btn"
                        @click="close"
                        v-if="!createFolderInputVisible">
                        <span>{{ __('fairu::browser.cancel') }}</span>
                    </button>
                    <button
                        class="flex items-center gap-1 btn btn-primary"
                        @click="sendSelection"
                        v-if="!createFolderInputVisible">
                        <span>{{ __('fairu::browser.select') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </stack>
</template>

<script>
import { RingLoader } from 'vue-spinners-css';
import Dropzone from './Dropzone.vue';
import BrowserListItem from './browser/BrowserListItem.vue';
import InputCheckbox from './input/InputCheckbox.vue';
import { fairuLoadFolder, fairuUpload } from '../utils/fetches';
import axios from 'axios';

export default {
    mixins: [Fieldtype],

    components: {
        RingLoader,
        Dropzone,
        BrowserListItem,
        InputCheckbox,
    },

    data() {
        return {
            assets: [],
            loading: false,
            folder: null,
            page: 1,
            lastPage: 1,
            loadingList: false,
            showSelection: false,
            percentUploaded: 0,
            folderContent: null,
            createFolderInputVisible: false,
        };
    },
    props: {
        meta: null,
        config: null,
        initialAssets: [],
        selectionType: {
            type: 'folder' | 'files',
            default: 'files',
        },
        multiselect: Boolean,
    },
    methods: {
        emitClose() {
            this.$emit('close');
        },
        openFile() {
            this.$refs.upload.value = null;
            this.$refs.upload.click();
        },
        openCreateFolder() {
            this.createFolderInputVisible = true;
        },
        closeCreateFolder() {
            this.createFolderInputVisible = false;
            this.newFolderName = null;
        },
        selectFolder(folderId) {
            this.page = 1;
            this.loadFolder(null, folderId);
            this.$refs.search.value = null;
        },
        selectItem(asset) {
            this.$emit('selected', asset);
            this.$nextTick(() => {
                this.emitClose();
            });
        },
        sendSelection() {
            this.$emit('selected', this.selectionType === 'folder' ? this.folder : this.assets);
            this.$nextTick(() => {
                this.emitClose();
            });
        },
        toggleItemSelection(asset) {
            if (this.assets.find((e) => e?.id === asset.id)) {
                this.assets = this.assets.filter((e) => e?.id !== asset.id);
            } else {
                this.assets.push(asset);
            }
            if (this.assets.length < 1) this.showSelection = false;
        },
        toggleShowSelection() {
            this.showSelection = !this.showSelection;
        },
        clearSelection() {
            this.assets = [];
            this.showSelection = false;
        },
        toggleCurrentSelection() {
            if (this.showSelection) {
                this.clearSelection();
                return;
            }

            const folderFiles = this.folderContent?.data.filter((e) => e.type !== 'folder');
            if (
                folderFiles.every((file) => this.assets.map((e) => e.id).includes(file.id)) ||
                (this.config.max_files && this.assets?.length >= this.config.max_files)
            ) {
                this.assets = this.assets.filter((asset) => !folderFiles.map((file) => file.id).includes(asset.id));
            } else {
                const newItems = folderFiles.filter((file) => !this.assets.map((e) => e.id).includes(file.id));
                if (!newItems) return;

                const remainingSlots = Math.max(0, this.config.max_files - this.assets.length);

                this.assets.push(...newItems.slice(0, remainingSlots));
            }
        },
        isSelected(asset) {
            return this.assets?.findIndex((e) => e?.id === asset.id) > -1;
        },
        handleFileChange(evt) {
            const files = evt.target.files;
            this.handleUpload(files);
        },
        handleFileDrop(files) {
            if (!files) return;

            this.handleUpload(files);
        },
        handleUpload(files) {
            const errorCallback = (err) => {
                this.loading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.error(err.response.data.message);
                this.$refs.upload.value = null;
            };

            const successCallback = (result) => {
                this.$progress.complete('upload' + this._uid);
                this.$toast.success(__('fairu::browser.files_uploaded_successfully'));
                this.fetchingMetaData = true;
                this.$nextTick(async () => {
                    const fetchedAssets = await this.loadMetaData(result?.data?.map((e) => e.id));
                    if (this.multiselect) {
                        if (fetchedAssets?.length > 0) {
                            const remainingSlots = Math.max(0, this.config.max_files - this.assets.length);

                            this.assets.push(...fetchedAssets.slice(0, remainingSlots));
                        }
                        await this.loadFolder();
                    } else {
                        this.assets = fetchedAssets.slice(0, 1);
                        this.selectItem(this.assets[0]);
                    }
                    this.fetchingMetaData = false;
                });
            };
            this.$progress.start('upload' + this._uid);
            this.percentUploaded = 0;
            this.loading = true;

            fairuUpload({
                files,
                folder: this.folder?.id ?? null,
                onUploadProgressCallback: (progressEvent) => {
                    this.percentUploaded = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                },
                successCallback,
                errorCallback,
            });
        },
        handleCreateFolder() {
            this.loading = true;
            axios
                .post('/fairu/folders/create', {
                    name: this.newFolderName,
                    folder: this.folder?.id ?? null,
                })
                .then(async (result) => {
                    this.$nextTick(() => {
                        this.closeCreateFolder();
                        this.loadFolder();
                    });
                })
                .catch((error) => {
                    this.$toast.error(error.response.data.message);
                    this.loading = false;
                });
        },
        handleSearchInput(e) {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.page = 1;
                this.loadFolder(e.target.value);
            }, 250);
        },
        nextPage() {
            this.page = this.page + 1;
            this.loadFolder(this.$refs.search.value);
        },
        previousPage() {
            this.page = this.page - 1;
            this.loadFolder(this.$refs.search.value);
        },
        goToPage(page) {
            this.page = page;
            this.loadFolder(this.$refs.search.value);
        },
        async loadFolder(search, folderId) {
            this.loadingList = true;

            return await fairuLoadFolder({
                page: this.page,
                folder: folderId !== undefined ? folderId : this.folder?.id,
                search: search ?? null,
                successCallback: (result) => {
                    this.folder = result.data.entry;
                    this.folderContent = result.data.entries;
                    this.lastPage = result.data.entries?.last_page;

                    this.loadingList = false;
                    return result.data.entry;
                },
                errorCallback: () => {
                    this.folderContent = null;
                    this.folder = null;
                    this.loadingList = false;
                },
            });
        },
        async loadMetaData(ids) {
            if (!ids && !this.assets) return [];

            // Ensure ids is always an array
            const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);

            if (assetIds.length === 0) return [];

            this.loading = true;

            try {
                return axios
                    .post('/fairu/files/list', { ids: assetIds })
                    .then((result) => result.data)
                    .catch((err) => {
                        console.error(`${__('fairu::browser.errors.error_fetching_files')}:`, err);
                        return null;
                    });
            } catch (error) {
                console.error(`${__('fairu::browser.errors.error_fetching_files')}:`, error);
                return [];
            } finally {
                this.loading = false;
            }
        },

        canUpload() {
            return this.config.allow_uploads;
        },
    },

    computed: {
        url() {
            return this.meta.proxy + '/' + this.asset?.id + '/thumbnail.webp?width=50&height=50';
        },
    },

    async mounted() {
        this.assets = this.config.max_files === 1 ? [] : this.initialAssets;
        await this.loadFolder(null, this.config.folder);
    },
    beforeDestroy() {
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
            this.searchTimer = null;
        }

        if (this.$refs.search) {
            this.$refs.search.removeEventListener('input', this.handleSearchInput);
        }
    },
};
</script>
