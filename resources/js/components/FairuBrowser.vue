<template>
    <stack @closed="emitClose">
        <div
            slot-scope="{ close }"
            class="grid h-full bg-white dark:bg-dark-800"
            style="grid-template-rows: auto 1fr auto">
            <section>
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleUpload" />
                <div class="flex gap-2 p-2 bg-white dark:bg-dark-800 data-list-border justify-stretch">
                    <button
                        type="button"
                        class="flex items-center gap-1 text-base btn btn-primary"
                        @click="openFile(folder)"
                        v-if="!createFolderInputVisible">
                        <i class="text-gray-700 material-symbols-outlined">upload</i> <span>Upload</span>
                    </button>
                    <button
                        href="#"
                        class="flex items-center gap-1 text-base btn"
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
                            class="flex items-center gap-1 text-base btn btn-primary"
                            @click="handleCreateFolder">
                            <i class="text-lg text-gray-700 material-symbols-outlined">check_circle</i
                            ><span>Erstellen</span>
                        </button>
                        <button
                            class="flex items-center gap-1 text-base btn"
                            @click="closeCreateFolder">
                            <i class="text-lg text-gray-700 material-symbols-outlined">cancel</i><span>Abbrechen</span>
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
                        class="flex items-center gap-1 text-base btn"
                        v-if="!createFolderInputVisible">
                        <i class="text-gray-700 material-symbols-outlined">open_in_new</i>
                    </a>
                    <a
                        class="flex items-center gap-1 text-sm text-gray-700 link"
                        @click.prevent="close">
                        <i class="text-xl material-symbols-outlined">close</i>
                    </a>
                </div>
            </section>
            <div class="overflow-y-auto">
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
                        class="px-2 data-list-border"
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
                        class="px-2 data-list-border">
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
                                class="flex w-full gap-1 cursor-pointer grow"
                                @click.prevent="selectItem(item.id)">
                                <div
                                    class="flex items-center justify-center flex-none overflow-hidden bg-gray-300 rounded-full"
                                    style="width: 34px; height: 34px">
                                    <img
                                        v-if="proxyUrl && item?.blocked != true && item?.mime?.startsWith('image/')"
                                        :src="`${proxyUrl}/${item.id}/thumbnail.webp?width=34&height=34`"
                                        class="object-cover" />
                                    <span
                                        class="block text-gray-600"
                                        style="font-size: 8px"
                                        v-if="item?.mime?.startsWith('image/') == false">
                                        {{ getExtension(item.mime) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-sm grow"> {{ item.name }} </div>
                            </a>
                            <div class="flex gap-1">
                                <a
                                    :href="`${meta.file}${item.id}`"
                                    target="_blank"
                                    title="In Fairu bearbeiten"
                                    class="flex gap-1 text-xs cursor-pointer"
                                    ><i class="text-xl text-gray-300 material-symbols-outlined">edit</i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-4 px-4 py-2 border-t border-gray-100 dark:border-dark-600">
                <div class="flex items-center justify-end gap-1 -mt-px">
                    <button
                        :disabled="page <= 1"
                        @click.prevent="goToPage(1)"
                        class="flex items-center gap-1 text-base btn btn-sm">
                        <i class="text-xl text-dark-800 dark:text-gray-300 material-symbols-outlined">first_page</i>
                    </button>
                    <button
                        :disabled="page <= 1"
                        @click.prevent="previousPage"
                        class="flex items-center gap-1 text-base btn btn-sm">
                        Zurück
                    </button>
                    <div class="px-2">{{ page }} / {{ lastPage || 1 }}</div>
                    <button
                        :disabled="page >= lastPage"
                        @click.prevent="nextPage"
                        class="flex items-center gap-1 text-base btn btn-sm">
                        Weiter
                    </button>
                    <button
                        :disabled="page >= lastPage"
                        @click.prevent="goToPage(lastPage)"
                        class="flex items-center gap-1 text-base btn btn-sm">
                        <i class="text-xl text-gray-300 material-symbols-outlined">last_page</i>
                    </button>
                </div>
                <a
                    class="flex items-center gap-1 text-2xs"
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
                        alt="Fairu Asset Service" />
                </a>
            </div>
        </div>
    </stack>
</template>

<script>
import { RingLoader } from 'vue-spinners-css';
import Dropzone from './Dropzone.vue';
import { fairuLoadFolder, fairuUpload } from '../utils/fetches';
import axios from 'axios';

export default {
    mixins: [Fieldtype],

    components: {
        RingLoader,
        Dropzone,
    },

    data() {
        return {
            asset_id: null,
            searchOpen: false,
            loading: false,
            observer: null,
            folder: null,
            uploadFolder: false,
            loadingList: false,
            percentUploaded: 0,
            folderParent: null,
            folderContent: null,
            createFolderInputVisible: false,
        };
    },
    props: {
        proxyUrl: String,
    },
    methods: {
        emitClose() {
            this.$emit('close');
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
            this.emitClose();
        },
        handleUpload(evt) {
            const errorCallback = (error) => {
                this.loading = false;
                this.$progress.complete('upload' + this._uid);
                this.$toast.error(err.response.data.message);
                this.$refs.upload.value = null;
            };

            const successCallback = (result) => {
                this.asset_id = result.data.id;
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
            const file = evt.target.files[0];
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

            fairuLoadFolder({
                page: this.page,
                folder: this.folder,
                search: search ?? null,
                successCallback: (result) => {
                    this.folderParent = result.data.entry?.parent_id;
                    this.folderContent = result.data.entries;
                    this.lastPage = result.data.entries?.last_page;

                    this.loadingList = false;
                },
                errorCallback: () => {
                    this.folderContent = null;
                    this.folderParent = null;
                    this.loadingList = false;
                },
            });
        },
        loadMetaData() {
            this.loading = true;
            axios
                .get('/fairu/files/' + this.asset_id)
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
            return this.proxyUrl + '/' + this.asset_id + '/thumbnail.webp?width=50&height=50';
        },
    },

    mounted() {
        this.loadFolder();
    },
    beforeDestroy() {
        // Falls der Observer noch aktiv ist, beim Zerstören der Komponente aufräumen
        if (this.observer) {
            this.observer.disconnect();
        }
    },
};
</script>
