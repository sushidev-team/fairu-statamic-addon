<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :proxyUrl="meta.proxy" />
        <dropzone
            :enabled="canUpload"
            @dropped="handleFileDrop">
            <div
                v-if="!asset?.id && !loading && !uploading"
                class="assets-fieldtype">
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleUpload" />
                <div class="assets-fieldtype-drag-container">
                    <div class="flex flex-wrap items-center py-3 assets-fieldtype-picker gap-x-4">
                        <button
                            @click="openSearch()"
                            class="btn"
                            ><i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i> Durchsuchen
                        </button>
                        <p class="flex-1 asset-upload-control">
                            <button
                                type="button"
                                class="upload-text-button"
                                @click="openFile(null)">
                                Datei hochladen
                            </button>
                            <span class="drag-drop-text">oder per Drag &amp; Drop hierher ziehen.</span>
                        </p>
                    </div>
                </div>
            </div>
        </dropzone>
        <div
            :id="_uid"
            class="flex items-center gap-2 p-3 border rounded border-slate-400 fa-bg-slate-100"
            v-if="asset?.id || loading || uploading">
            <ring-loader
                color="#4a4a4a"
                class="w-5 h-5"
                size="24"
                v-if="loading || uploading" />
            <span v-if="uploading">{{ percentUploaded }}%</span>
            <span v-else-if="loading && fetchingMetaData">Meta-Daten werden ermittelt...</span>
            <div
                v-if="!loading && !uploading"
                class="grid w-full min-w-0 gap-2"
                style="grid-template-columns: auto 1fr auto">
                <img
                    ref="fileImage"
                    v-if="loading == false && !fetchingMetaData && asset?.mime.startsWith('image/')"
                    style="width: 50px; height: 50px"
                    class="flex-none overflow-hidden rounded-md"
                    :src="url" />
                <ring-loader
                    color="#4a4a4a"
                    class="w-5 h-5"
                    size="24"
                    v-if="fetchingMetaData" />
                <a
                    @click.prevent="openSearch"
                    class="grid w-full min-w-0 fa-content-center fa-items-center">
                    <div
                        class="min-w-0 text-sm truncate"
                        v-html="asset?.name"></div>
                    <div class="min-w-0 text-xs font-bold truncate">Ändern</div>
                </a>
                <div class="flex gap-1">
                    <a
                        :href="asset?.edit_url"
                        target="_blank"
                        class="text-xs text-gray-500 underline cursor-pointer hover:text-gray-900"
                        ><i class="text-lg material-symbols-outlined">edit</i></a
                    >
                    <a
                        class="text-xs text-gray-500 underline cursor-pointer hover:text-gray-900"
                        @click.prevent="clearAsset"
                        ><i class="text-lg material-symbols-outlined">delete</i></a
                    >
                </div>
            </div>
        </div>
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
            if (!id && !this.asset?.id) return;
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
