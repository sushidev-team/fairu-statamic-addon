<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="multiselect"
            :initialAssets="assets"
            :meta="meta" />
        <dropzone
            :enabled="canUpload"
            class="border rounded border-slate-400 dark:fa-bg-dark-900 fa-overflow-hidden fa-bg-slate-100 dark:fa-border-zinc-900 dark:fa-bg-zinc-800"
            @dropped="handleFileDrop">
            <div v-if="(multiselect || assets?.length < 1) && !loading && !uploading">
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleUpload" />
                <div>
                    <div class="flex flex-wrap items-center p-3 gap-x-4">
                        <button
                            @click="openSearch()"
                            class="btn"
                            ><i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i>
                            {{ __('fairu::fieldtype.search') }}
                        </button>
                        <p class="flex-1 text-xs">
                            <button
                                type="button"
                                class="underline text-blue"
                                @click="openFile(null)">
                                {{ __('fairu::fieldtype.upload_file') }}
                            </button>
                            <span class="fa-ml-1.5 fa-text-gray-500">{{
                                __('fairu::fieldtype.or_add_per_drag_and_drop')
                            }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div
                :id="_uid"
                class="flex items-center gap-2 fa-cursor-pointer"
                :class="
                    multiselect
                        ? 'p-2 fa-border-t fa-border-slate-300 fa-bg-white hover:fa-bg-white/70 dark:fa-border-zinc-700 dark:fa-bg-zinc-800 dark:hover:fa-bg-zinc-700/60'
                        : 'fa-p-2.5 hover:fa-bg-white/40 dark:fa-bg-zinc-800/40 dark:hover:fa-bg-zinc-700/60'
                "
                v-if="assets?.length > 0 || loading || uploading"
                @click.prevent="openSearch">
                <ring-loader
                    color="#4a4a4a"
                    class="w-5 h-5"
                    size="24"
                    v-if="loading || uploading" />
                <span v-if="uploading">{{ percentUploaded }}%</span>
                <div class="w-full">
                    <div
                        v-if="!loading && !uploading"
                        class="grid w-full min-w-0 gap-2 items-center fa-mt-2 fa-grid-cols-[auto,1fr,auto] fa-border-t fa-border-zinc-200 fa-pt-2 first:fa-mt-0 first:fa-border-none first:fa-pt-0 dark:fa-border-zinc-700"
                        v-for="(item, index) in assets">
                        <img
                            ref="fileImage"
                            v-if="loading == false && !fetchingMetaData && item?.mime.startsWith('image/')"
                            class="flex-none overflow-hidden rounded-md"
                            :class="multiselect ? 'fa-size-8' : 'fa-size-10'"
                            :src="meta.proxy + '/' + item?.id + '/thumbnail.webp?width=50&height=50'" />
                        <ring-loader
                            color="#4a4a4a"
                            class="w-5 h-5"
                            size="24"
                            v-if="fetchingMetaData" />
                        <div class="w-full min-w-0 fa-content-center fa-items-center">
                            <div
                                class="min-w-0 text-xs truncate"
                                v-html="item?.name"></div>
                        </div>
                        <div class="flex items-center gap-1">
                            <a
                                @click.stop
                                :href="item?.edit_url"
                                target="_blank"
                                title="Open in Fairu"
                                class="flex items-center text-xs cursor-pointer hover:text-blue !fa-text-gray-300 dark:!fa-text-zinc-500"
                                ><i class="text-lg material-symbols-outlined">open_in_new</i></a
                            >
                            <button
                                class="flex items-center text-xs cursor-pointer !fa-text-gray-300 hover:!fa-text-gray-800 dark:!fa-text-zinc-500 dark:hover:!fa-text-zinc-100"
                                title="Remove"
                                @click.prevent.stop="clearAsset(item)"
                                ><i class="text-lg material-symbols-outlined">delete</i></button
                            >
                        </div>
                    </div>
                </div>
            </div>
        </dropzone>
    </div>
</template>

<script>
import axios from 'axios';
import { RingLoader } from 'vue-spinners-css';
import FairuBrowser from './FairuBrowser.vue';
import Dropzone from './Dropzone.vue';
import { fairuUpload } from '../utils/fetches';

export default {
    mixins: [Fieldtype],

    components: {
        RingLoader,
        FairuBrowser,
        Dropzone,
    },

    data() {
        return {
            assets: null,
            searchOpen: false,
            multiselect: false,
            loading: true,
            loadingList: false,
            uploading: false,
            percentUploaded: null,
            fetchingMetaData: false,
        };
    },

    methods: {
        openSearch() {
            this.searchOpen = true;
        },
        getExtension(mime) {
            const parts = mime.split('/');
            if (parts.length == 2) {
                return parts[1];
            }
            return 'n/a';
        },
        openFile(setFolderTo) {
            this.uploadFolder = setFolderTo ?? null;
            this.$refs.upload.value = null;
            this.$refs.upload.click();
        },
        clearAsset(item) {
            this.assets = this.assets.filter((e) => e.id !== item.id);
            this.$nextTick(() => {
                this.sendUpdate();
            });
        },
        handleSelected(assets) {
            this.assets = this.multiselect ? assets : [assets];
            this.$nextTick(() => {
                this.sendUpdate();
            });
        },
        handleFileChange(evt) {
            const file = evt.target.files[0];
            this.handleUpload(file);
        },
        handleFileDrop(files) {
            const file = files?.[0];
            if (!file) return;

            this.handleUpload(file);
        },
        handleUpload(file) {
            this.$progress.start('upload' + this._uid);
            this.percentUploaded = 0;
            this.uploading = true;

            const successCallback = (result) => {
                this.searchOpen = false;
                this.uploading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.success('Datei erfolgreich hochgeladen.');
                this.fetchingMetaData = true;
                this.$nextTick(async () => {
                    const fetchedAssets = await this.loadMetaData(result?.data?.id);
                    if (this.multiselect) {
                        this.assets.push(...fetchedAssets);
                    } else {
                        this.assets = [fetchedAssets?.[0]];
                    }
                    this.sendUpdate();
                    this.fetchingMetaData = false;
                });
            };

            const errorCallback = (err) => {
                this.uploading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.error(err.response.data.message);
                this.$refs.upload.value = null;
            };

            fairuUpload({
                file,
                folder: this.uploadFolder != null ? this.uploadFolder : null,
                onUploadProgressCallback: (progressEvent) => {
                    this.percentUploaded = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                },
                successCallback,
                errorCallback,
            });
        },
        sendUpdate() {
            this.update(this.multiselect ? this.assets?.map((e) => e.id) : this.assets[0]?.id);
        },
        async loadMetaData(ids) {
            let assetIds = [];
            if (!ids && !this.assets) {
                this.loading = false;
                return []; // Return empty array instead of undefined
            }

            // If ids is a single value (not array) or an array
            if (Array.isArray(ids)) {
                assetIds = ids;
            } else if (ids) {
                assetIds = [ids];
            } else {
                // If this.assets exists but ids doesn't
                assetIds = this.multiselect ? this.assets.map((a) => a.id) : [this.assets[0]?.id];
            }

            this.loading = true;
            let fetchedAssets = [];

            try {
                await Promise.all(
                    assetIds.map((id) =>
                        axios
                            .get('/fairu/files/' + id)
                            .then((result) => {
                                fetchedAssets.push(result.data.data);
                            })
                            .catch((err) => {
                                console.error(`Error fetching asset ${id}:`, err);
                                this.$toast.error(err.response?.data?.message || 'Failed to load file');
                                return Promise.resolve();
                            }),
                    ),
                );

                if (fetchedAssets.length > 0) {
                    this.folder = fetchedAssets[0]?.parent_id;
                }
            } catch (error) {
                console.error('Error in loadMetaData:', error);
            } finally {
                this.loading = false;
            }

            return fetchedAssets;
        },
        canBrowse() {
            const hasPermission =
                this.can('configure asset containers') || this.can('view ' + this.container + ' assets');

            if (!hasPermission) return false;

            return !this.hasPendingDynamicFolder;
        },

        canUpload() {
            const hasPermission =
                this.can('configure asset containers') || this.can('upload ' + this.container + ' assets');

            if (!hasPermission) return false;

            return !this.hasPendingDynamicFolder;
        },
    },

    computed: {},
    async mounted() {
        this.multiselect = this.config.max_files !== 1;
        this.assets = await this.loadMetaData(this.value);
    },
    beforeDestroy() {},
};
</script>
