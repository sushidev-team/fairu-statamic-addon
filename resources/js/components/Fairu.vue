<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :proxyUrl="meta.proxy" />
        <dropzone
            :enabled="canUpload"
            class="border rounded border-slate-400 dark:fa-bg-dark-900 fa-overflow-hidden fa-bg-slate-100"
            @dropped="handleFileDrop">
            <div v-if="(this.config.max_files !== 1 || !asset?.id) && !loading && !uploading">
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
                    this.config.max_files === 1
                        ? 'fa-p-2.5 hover:fa-bg-white/40'
                        : 'p-2 fa-border-t fa-border-slate-300 fa-bg-white hover:fa-bg-white/70'
                "
                v-if="asset?.id || loading || uploading"
                @click.prevent="openSearch">
                <ring-loader
                    color="#4a4a4a"
                    class="w-5 h-5"
                    size="24"
                    v-if="loading || uploading" />
                <span v-if="uploading">{{ percentUploaded }}%</span>
                <span v-else-if="loading && fetchingMetaData">{{ __('fairu::fieldtype.meta_data_fetching') }}</span>
                <div
                    v-if="!loading && !uploading"
                    class="grid w-full min-w-0 gap-2 items-center fa-grid-cols-[auto,1fr,auto]">
                    <img
                        ref="fileImage"
                        v-if="loading == false && !fetchingMetaData && asset?.mime.startsWith('image/')"
                        class="flex-none overflow-hidden rounded-md"
                        :class="this.config.max_files === 1 ? 'fa-size-10' : 'fa-size-8'"
                        :src="url" />
                    <ring-loader
                        color="#4a4a4a"
                        class="w-5 h-5"
                        size="24"
                        v-if="fetchingMetaData" />
                    <div class="w-full min-w-0 fa-content-center fa-items-center">
                        <div
                            class="min-w-0 text-xs truncate"
                            v-html="asset?.name"></div>
                    </div>
                    <div class="flex items-center gap-1 text-gray-500">
                        <a
                            @click.stop
                            :href="asset?.edit_url"
                            target="_blank"
                            title="Open in Fairu"
                            class="flex items-center text-xs text-gray-500 cursor-pointer hover:text-blue"
                            ><i class="text-lg material-symbols-outlined">open_in_new</i></a
                        >
                        <button
                            class="flex items-center text-xs cursor-pointer hover:fa-text-red-500"
                            title="Remove"
                            @click.prevent.stop="clearAsset"
                            ><i class="text-lg material-symbols-outlined">delete</i></button
                        >
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
            searchOpen: false,
            loading: true,
            loadingList: false,
            uploading: false,
            asset: null,
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
        clearAsset() {
            this.asset = null;
            this.$nextTick(() => {
                this.sendUpdate();
            });
        },
        handleSelected(asset) {
            this.fetchingMetaData = true;
            this.asset = asset;
            this.$nextTick(() => {
                this.sendUpdate();
                this.fetchingMetaData = false;
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
                this.asset = result.data.data;
                this.searchOpen = false;
                this.uploading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.success('Datei erfolgreich hochgeladen.');
                this.fetchingMetaData = true;
                this.$nextTick(async () => {
                    await this.loadMetaData(result?.data?.id);
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
            this.update(this.asset?.id);
        },
        async loadMetaData(id) {
            if (!id && !this.asset?.id) {
                this.loading = false;
                return;
            }
            this.loading = true;
            await axios
                .get('/fairu/files/' + (id ?? this.asset?.id))
                .then((result) => {
                    this.asset = result.data.data;
                    this.folder = result.data.parent_id;
                    this.loading = false;
                })
                .catch((err) => {
                    this.asset = null;
                    this.loading = false;
                    this.$toast.error(err.response.data.message);
                });
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

    computed: {
        url() {
            return this.meta.proxy + '/' + this.asset?.id + '/thumbnail.webp?width=50&height=50';
        },
    },
    mounted() {
        this.asset = this.loadMetaData(this.value);
    },
    beforeDestroy() {},
};
</script>
