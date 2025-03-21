<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            :proxyUrl="meta.proxy" />
        <dropzone
            ref="uploader"
            :enabled="canUpload"
            @dropped="handleFileDrop">
            <div
                v-if="!asset_id && !loading"
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
            class="flex items-center gap-2"
            v-if="(asset_id != null && asset_id != undefined) || loading == true">
            <ring-loader
                color="#4a4a4a"
                class="w-5 h-5"
                size="24"
                v-if="loading" />
            <span v-if="loading == true && syncingMeta == false">{{ percentUploaded }}%</span>
            <span v-if="loading == true && syncingMeta == true">Meta-Daten werden ermittelt...</span>
            <div
                v-if="!loading"
                class="grid w-full min-w-0 gap-2"
                style="grid-template-columns: auto 1fr auto">
                <img
                    ref="fileImage"
                    v-if="loading == false && asset?.mime.startsWith('image/')"
                    style="width: 50px; height: 50px"
                    class="flex-none overflow-hidden rounded-md"
                    :data-src="url" />
                <a
                    @click.prevent="(openSearch(), loadFolder(null, asset?.parent_id))"
                    class="w-full min-w-0">
                    <div
                        class="min-w-0 text-sm font-semibold truncate"
                        v-html="asset?.name"></div>
                    <div class="min-w-0 text-sm font-bold truncate">Ändern</div>
                </a>
                <div class="flex gap-1">
                    <a
                        :href="asset?.edit_url"
                        target="_blank"
                        class="text-xs text-gray-500 underline cursor-pointer"
                        ><i class="text-lg text-gray-300 material-symbols-outlined">edit</i></a
                    >
                    <a
                        class="text-xs text-gray-500 underline cursor-pointer"
                        @click.prevent="clearAsset"
                        ><i class="text-lg text-gray-300 material-symbols-outlined">delete</i></a
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
            observer: null,
            loadingList: false,
            asset_id: null,
            asset: null,
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
            this.asset_id = null;
            this.asset = null;
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
            const errorCallback = (error) => {
                this.loading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.error(err.response.data.message);
                this.$refs.upload.value = null;
            };

            const successCallback = (result) => {
                this.asset_id = result.data.id;
                this.asset = result.data.data;
                this.searchOpen = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.success('Datei erfolgreich hochgeladen.');
                this.$nextTick(() => {
                    this.sendUpdate();
                    this.loadMetaData();
                });
            };
            this.$forceUpdate();
            this.$progress.start('upload' + this._uid);
            this.percentUploaded = 0;
            this.loading = true;

            fairuUpload({
                file,
                folder: this.uploadFolder != null ? this.uploadFolder : null,
                onUploadProgressCallback: (progressEvent) => {
                    this.percentUploaded = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    this.$forceUpdate();
                },
                successCallback,
                errorCallback,
            });
        },
        sendUpdate() {
            this.update(this.asset_id);
        },
        loadMetaData() {
            this.loading = true;
            axios
                .get('/fairu/files/' + this.asset_id)
                .then((result) => {
                    try {
                        this.asset = result.data.data;
                        this.folder = result.data.parent_id;
                        this.loading = false;

                        this.$nextTick(() => {
                            if (this.$refs.fileImage && this.asset?.mime?.startsWith('image/')) {
                                this.$refs.fileImage.setAttribute('src', this.$refs.fileImage.getAttribute('data-src'));
                            }
                        });
                    } catch (err) {
                        this.asset_id = null;
                        this.$toast.error(err.response.data.message);
                        this.loading = false;
                    }
                })
                .catch((err) => {
                    this.asset_id = null;
                    this.loading = false;
                    this.$toast.error(err.response.data.message);
                });
        },

        loadObserver() {
            const element = document.getElementById(this._uid);

            const options = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1,
            };

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        this.loadMetaData();
                        this.observer.unobserve(entry.target);
                    }
                });
            }, options);

            if (element) {
                this.observer.observe(element);
            }
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
            return this.meta.proxy + '/' + this.asset_id + '/thumbnail.webp?width=50&height=50';
        },
    },

    mounted() {
        this.asset_id = this.value;
        if (this.asset_id != null) {
            setTimeout(() => {
                this.loadObserver();
            });
        } else {
            this.loading = false;
        }
    },
    beforeDestroy() {
        // Falls der Observer noch aktiv ist, beim Zerstören der Komponente aufräumen
        if (this.observer) {
            this.observer.disconnect();
        }
    },
};
</script>
