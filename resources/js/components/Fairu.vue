<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            :proxyUrl="meta.proxy" />
        <dropzone
            ref="uploader"
            :enabled="canUpload"
            :path="folder"
            @updated="uploadsUpdated"
            @upload-complete="uploadComplete"
            @error="uploadError">
            <div
                v-if="(asset_id == null || asset_id == undefined) && loading == false"
                class="assets-fieldtype">
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleFileChange" />
                <div class="assets-fieldtype-drag-container">
                    <div class="assets-fieldtype-picker flex flex-wrap items-center gap-x-4 py-3">
                        <button
                            @click="openSearch()"
                            class="btn"
                            ><i class="material-symbols-outlined mr-2 inline-block">drive_folder_upload</i> Durchsuchen
                        </button>
                        <p class="asset-upload-control flex-1">
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
                class="h-5 w-5"
                size="24"
                v-if="loading" />
            <span v-if="loading == true && syncingMeta == false">{{ percentUploaded }}%</span>
            <span v-if="loading == true && syncingMeta == true">Meta-Daten werden ermittelt...</span>
            <div
                v-if="loading == false"
                class="grid w-full min-w-0 gap-2"
                style="grid-template-columns: auto 1fr auto">
                <img
                    ref="fileImage"
                    v-if="loading == false && asset_data.mime.startsWith('image/')"
                    style="width: 50px; height: 50px"
                    class="flex-none overflow-hidden rounded-md"
                    :data-src="url" />
                <a
                    @click.prevent="(openSearch(), loadFolder(null, asset_data?.parent_id))"
                    class="w-full min-w-0">
                    <div
                        class="min-w-0 truncate text-sm font-semibold"
                        v-html="asset_data?.name"></div>
                    <div class="min-w-0 truncate text-sm font-bold">Ändern</div>
                </a>
                <div class="flex gap-1">
                    <a
                        :href="asset_data?.edit_url"
                        target="_blank"
                        class="text-gray-500 cursor-pointer text-xs underline"
                        ><i class="text-gray-300 material-symbols-outlined text-lg">edit</i></a
                    >
                    <a
                        class="text-gray-500 cursor-pointer text-xs underline"
                        @click.prevent="clearAsset"
                        ><i class="text-gray-300 material-symbols-outlined text-lg">delete</i></a
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

export default {
    mixins: [Fieldtype],

    components: {
        RingLoader,
        FairuBrowser,
        Dropzone,
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
            this.asset_data = null;
            this.$nextTick(() => {
                this.sendUpdate();
            });
        },
        handleFileChange() {
            this.percentUploaded = 0;
            this.loading = true;
            this.$forceUpdate();
            this.$progress.start('upload' + this._uid);
            const file = event.target.files[0];
            axios
                .post(this.meta.upload, {
                    portal: this.portal,
                    filename: file.name,
                    mime: file.type,
                    folder: this.uploadFolder != null ? this.uploadFolder : null,
                })
                .then(async (result) => {
                    let resultUpload = await axios
                        .put(result.data.upload_url, file, {
                            headers: {
                                'x-amz-acl': 'public-read',
                                'Content-Type': file.type?.toString(),
                            },
                            onUploadProgress: (progressEvent) => {
                                this.percentUploaded = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                                this.$forceUpdate();
                            },
                        })
                        .then((resultUpload) => {
                            this.asset_id = result.data.id;
                            this.searchOpen = false;

                            this.syncingMeta = true;
                            axios.get(result.data.sync_url).then(() => {
                                this.$toast.success('Datei erfolgreich hochgeladen.');
                                this.$progress.complete('upload' + this._uid);
                                this.syncingMeta = false;
                                this.$nextTick(() => {
                                    this.sendUpdate();
                                    this.loadMetaData();
                                });
                            });
                        })
                        .catch((error) => {
                            this.$toast.error('Es ist ein Fehler beim Upload zu Fairu aufgetreten');
                            this.$progress.complete('upload' + this._uid);
                            this.$refs.upload.value = null;
                        });
                })
                .catch((error) => {
                    this.$toast.error(error.response.data.message);
                    this.loading = false;
                    this.$progress.complete('upload' + this._uid);
                    this.$refs.upload.value = null;
                });
        },
        sendUpdate() {
            this.update(this.asset_id);
        },
        loadMetaData() {
            this.loading = true;
            axios
                .get(this.meta.api + this.asset_id)
                .then((result) => {
                    try {
                        this.asset_data = result.data.data;
                        this.folder = result.data.parent_id;

                        setTimeout(() => {
                            this.$refs.fileImage.setAttribute('src', this.$refs.fileImage.getAttribute('data-src'));
                        }, 500);
                    } catch (err) {
                        this.asset_id = null;
                        this.$toast.error(err.response.data.message);
                    }
                    this.loading = false;
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
                root: null, // Standard: das Browser-Viewport
                rootMargin: '0px',
                threshold: 0.1,
            };

            // Callback, das aufgerufen wird, sobald sich Sichtbarkeit ändert
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    // Falls das Element zu mindestens 10% (threshold = 0.1) sichtbar ist
                    if (entry.isIntersecting) {
                        this.loadMetaData();
                        this.observer.unobserve(entry.target);
                    }
                });
            }, options);

            // Los geht's: Beobachten des referenzierten Divs starten
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

    data() {
        return {
            searchOpen: false,
            loading: false,
            observer: null,
            loadingList: false,
        };
    },

    computed: {
        url() {
            return this.meta.proxy + '/' + this.asset_id + '/thumbnail.webp?width=50&height=50';
        },
    },

    mounted() {},
    beforeDestroy() {
        // Falls der Observer noch aktiv ist, beim Zerstören der Komponente aufräumen
        if (this.observer) {
            this.observer.disconnect();
        }
    },
};
</script>
