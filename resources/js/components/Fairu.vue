<template>
    <div>
        <stack
            v-if="searchOpen"
            @closed="
                searchOpen = false;
                uploadFolder = null;
            ">
            <div
                slot-scope="{ close }"
                class="bg-white grid h-full"
                style="grid-template-rows: auto 1fr auto">
                <section>
                    <div class="bg-gray-100 data-list-border flex justify-stretch gap-2 p-2">
                        <button
                            href="#"
                            class="btn btn-primary flex items-center gap-1 text-base"
                            @click="openFile(folder)"
                            v-if="!createFolderInputVisible">
                            <i class="text-gray-700 material-symbols-outlined">upload</i> <span>Upload</span>
                        </button>
                        <button
                            href="#"
                            class="btn flex items-center gap-1 text-base"
                            @click="openCreateFolder"
                            v-if="!createFolderInputVisible">
                            <i class="text-gray-700 material-symbols-outlined">create_new_folder</i>
                            <span>Neuer Ordner</span>
                        </button>
                        <div
                            v-if="createFolderInputVisible"
                            class="flex flex-grow gap-1">
                            <input
                                class="input-text"
                                type="text"
                                ref="newfolder"
                                placeholder="Neuer Ordnername"
                                v-model="newFolderName" />
                            <button
                                class="btn btn-primary flex items-center gap-1 text-base"
                                @click="handleCreateFolder">
                                <i class="text-gray-700 material-symbols-outlined text-lg">check_circle</i
                                ><span>Erstellen</span>
                            </button>
                            <button
                                class="btn flex items-center gap-1 text-base"
                                @click="closeCreateFolder">
                                <i class="text-gray-700 material-symbols-outlined text-lg">cancel</i
                                ><span>Abbrechen</span>
                            </button>
                        </div>
                        <input
                            v-if="!createFolderInputVisible"
                            class="input-text"
                            type="text"
                            ref="search"
                            placeholder="Suche in Ordner"
                            @input="handleSearchInput" />
                        <a
                            :href="`${meta.folder}${folder != null ? '/' + folder : ''}`"
                            target="_blank"
                            class="btn flex items-center gap-1 text-base"
                            v-if="!createFolderInputVisible">
                            <i class="text-gray-700 material-symbols-outlined">open_in_new</i>
                        </a>
                        <a
                            class="text-gray-700 link flex items-center gap-1 text-sm"
                            @click.prevent="close">
                            <i class="material-symbols-outlined text-xl">close</i>
                        </a>
                    </div>
                </section>
                <div class="overflow-y-auto">
                    <div
                        v-if="loadingList"
                        class="grid h-full w-full items-center justify-center p-8">
                        <ring-loader
                            color="#4a4a4a"
                            class="h-5 w-5"
                            size="24"
                            v-if="loadingList" />
                    </div>
                    <div v-show="!loadingList">
                        <div
                            class="data-list-border px-2"
                            v-if="folder">
                            <a
                                href="#"
                                class="flex items-center gap-1 px-2 py-1"
                                style="min-height: 3rem"
                                @click="selectFolder(folderParent)">
                                <i class="text-gray-700 material-symbols-outlined">folder</i> ...
                            </a>
                        </div>
                        <div
                            v-for="(item, index) in folderContent?.data"
                            v-key="item?.id"
                            class="data-list-border px-2">
                            <a
                                v-if="item.type == 'folder'"
                                href="#"
                                class="flex items-center gap-1 px-2 py-1"
                                style="min-height: 3rem"
                                @click="selectFolder(item.id)">
                                <i class="text-gray-700 material-symbols-outlined">folder</i> {{ item.name }}
                            </a>
                            <div
                                class="grid items-center gap-2 px-2 py-1"
                                style="min-height: 3rem; grid-template-columns: 1fr auto"
                                v-if="item.type !== 'folder'">
                                <a
                                    href="#"
                                    class="flex w-full grow cursor-pointer gap-1"
                                    @click.prevent="selectItem(item.id)">
                                    <div
                                        class="bg-gray-300 flex flex-none items-center justify-center overflow-hidden rounded-full"
                                        style="width: 34px; height: 34px">
                                        <img
                                            v-if="
                                                meta.proxy && item?.blocked != true && item?.mime?.startsWith('image/')
                                            "
                                            :src="`${meta.proxy}/${item.id}/thumbnail.webp?width=34&height=34`"
                                            class="object-cover" />
                                        <span
                                            class="text-gray-600 block"
                                            style="font-size: 8px"
                                            v-if="item?.mime?.startsWith('image/') == false">
                                            {{ getExtension(item.mime) }}
                                        </span>
                                    </div>
                                    <div class="flex grow items-center gap-2 text-sm"> {{ item.name }} </div>
                                </a>
                                <div class="flex gap-1">
                                    <a
                                        :href="`${meta.file}${item.id}`"
                                        target="_blank"
                                        title="In Fairu bearbeiten"
                                        class="flex cursor-pointer gap-1 text-xs"
                                        ><i class="text-gray-300 material-symbols-outlined text-xl">edit</i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-4 border-t px-4 py-2">
                    <div class="-mt-px flex items-center justify-end gap-1">
                        <button
                            :disabled="page <= 1"
                            @click.prevent="goToPage(1)"
                            class="btn btn-sm flex items-center gap-1 text-base">
                            <i class="text-gray-300 material-symbols-outlined text-xl">first_page</i>
                        </button>
                        <button
                            :disabled="page <= 1"
                            @click.prevent="previousPage"
                            class="btn btn-sm flex items-center gap-1 text-base">
                            Zurück
                        </button>
                        <div class="px-2">{{ page }} / {{ lastPage }}</div>
                        <button
                            :disabled="page >= lastPage"
                            @click.prevent="nextPage"
                            class="btn btn-sm flex items-center gap-1 text-base">
                            Weiter
                        </button>
                        <button
                            :disabled="page >= lastPage"
                            @click.prevent="goToPage(lastPage)"
                            class="btn btn-sm flex items-center gap-1 text-base">
                            <i class="text-gray-300 material-symbols-outlined text-xl">last_page</i>
                        </button>
                    </div>
                    <a
                        class="text-2xs flex items-center gap-1"
                        href="https://fairu.app"
                        style="color: #666">
                        <span
                            class="uppercase"
                            style="letter-spacing: 0.1rem"
                            >Powered by</span
                        >
                        <img
                            class="ml-1 h-auto w-12"
                            src="../../svg/fairu-logo.svg"
                            alt="Fairu Asset Service" />
                    </a>
                </div>
            </div>
        </stack>
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
                            @click="(openSearchNow(), loadFolder())"
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
                    @click.prevent="(openSearchNow(), loadFolder(null, asset_data?.parent_id))"
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
import Dropzone from './Dropzone.vue';

export default {
    mixins: [Fieldtype],

    components: {
        RingLoader,
        Dropzone,
    },

    methods: {
        openSearchNow() {
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
        openCreateFolder() {
            this.createFolderInputVisible = true;
        },
        closeCreateFolder() {
            this.createFolderInputVisible = false;
            this.newFolderName = null;
        },
        clearAsset() {
            this.asset_id = null;
            this.asset_data = null;
            this.$nextTick(() => {
                this.sendUpdate();
            });
        },
        selectFolder(folderId) {
            this.folder = folderId;
            this.page = 1;
            this.loadFolder();
            this.$refs.search.value = null;
        },
        selectItem(id) {
            this.asset_id = id;
            this.$nextTick(() => {
                this.sendUpdate();
                this.loadMetaData();
            });
            this.searchOpen = false;
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
        handleCreateFolder() {
            this.loading = true;
            axios
                .post('/fairu/folders/create', {
                    name: this.newFolderName,
                    folder: this.folder,
                })
                .then(async (result) => {
                    this.$nextTick(() => {
                        this.loadMetaData();
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
        sendUpdate() {
            this.update(this.asset_id);
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
        loadFolder(search, folder = null) {
            this.folder = folder ?? this.folder;
            this.loadingList = true;

            axios
                .post('/fairu/folders', {
                    page: this.page,
                    folder: this.folder,
                    portal: this.portal,
                    search: search ?? null,
                })
                .then((result) => {
                    try {
                        this.folderParent = result.data.entry?.parent_id;
                        this.folderContent = result.data.entries;
                        this.lastPage = result.data.entries?.last_page;
                    } catch (err) {
                        this.folderContent = null;
                        this.$toast.error(err.response.data.message);
                    }
                    this.loadingList = false;
                })
                .catch((err) => {
                    console.error(err);
                    this.folderContent = null;
                    this.folderParent = null;
                    this.loadingList = false;
                    this.$toast.error(err.response.data.message);
                });
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
