<template>
    <div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="multiselect"
            :initialAssets="assets"
            :meta="meta"
            :config="config" />
        <dropzone
            :enabled="canUpload"
            class="border rounded border-slate-400 dark:fa-bg-dark-900 fa-overflow-hidden fa-bg-slate-100 dark:fa-border-zinc-900 dark:fa-bg-zinc-800"
            @dropped="handleFileDrop">
            <div v-if="(multiselect || assets?.length < 1) && !uploading">
                <input
                    class="hidden"
                    type="file"
                    ref="upload"
                    @change="handleUpload" />
                <div class="flex flex-wrap items-center p-3 gap-x-4">
                    <button
                        @click="openSearch()"
                        class="btn"
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
                <div
                    class="w-full"
                    v-if="!loading && !uploading">
                    <div
                        class="text-xs fa-divide-x fa-divide-gray-200 fa-text-gray-400 dark:fa-divide-zinc-700 dark:fa-text-zinc-500"
                        v-if="(config.max_files && config.max_files !== 1) || config.min_files"
                        ><span
                            class="fa-pr-2"
                            v-if="config.min_files"
                            >{{ __('fairu::fieldtype.rules.min') }}: {{ config.min_files }}</span
                        ><span
                            class="fa-pl-2 first:fa-pl-0"
                            v-if="config.max_files"
                            >{{ __('fairu::fieldtype.rules.max') }}: {{ config.max_files }}</span
                        ></div
                    >
                    <div
                        class="grid w-full min-w-0 gap-2 items-center fa-mt-2 fa-grid-cols-[auto,auto,1fr,auto] fa-border-t fa-border-zinc-200 fa-pt-2 first:fa-mt-0 first:fa-border-none first:fa-pt-0 dark:fa-border-zinc-700"
                        v-for="(item, index) in assets"
                        :key="item.id + index">
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
                            ref="fileImage"
                            v-if="
                                loading == false &&
                                !metaItemsFetching.has(item.id) &&
                                item?.mime?.match(/^(image|video)\//)
                            "
                            :key="item.id + index + 'image'"
                            class="flex-none overflow-hidden rounded-md"
                            :class="multiselect ? 'fa-size-8' : 'fa-size-10'"
                            :src="meta.proxy + '/' + item?.id + '/thumbnail.webp?width=50&height=50'" />
                        <ring-loader
                            color="#4a4a4a"
                            class="w-5 h-5"
                            size="24"
                            v-if="metaItemsFetching.has(item.id)" />
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
                                @click.stop
                                :href="meta.file + '/' + item?.id"
                                target="_blank"
                                :title="__('fairu::fieldtype.open_in_fairu')"
                                class="flex items-center text-xs cursor-pointer hover:text-blue !fa-text-gray-300 dark:!fa-text-zinc-500"
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
        </dropzone>
    </div>
</template>

<script>
import axios from "axios";
import { RingLoader } from "vue-spinners-css";
import FairuBrowser from "../FairuBrowser.vue";
import Dropzone from "../Dropzone.vue";
import { fairuUpload } from "../../utils/fetches";

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
			metaItemsFetching: new Set(),
		};
	},

	methods: {
		openSearch() {
			this.searchOpen = true;
		},
		getExtension(mime) {
			const parts = mime.split("/");
			if (parts.length == 2) {
				return parts[1];
			}
			return "n/a";
		},
		getSize(item) {
			if (!item?.size) return null;
			return (item.size / 1024 / 1024).toFixed(2) + " MB";
		},
		isAvailable(item) {
			return item?.exists && !item?.locked;
		},
		openFile() {
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
			const files = evt.target.files;
			this.handleUpload(files);
		},
		handleFileDrop(files) {
			if (!files) return;

			this.handleUpload(files);
		},
		handleUpload(files) {
			this.$progress.start("upload" + this._uid);
			this.percentUploaded = 0;
			this.uploading = true;

			const successCallback = (result) => {
				this.searchOpen = false;
				this.uploading = false;
				this.$progress.complete("upload" + this._uid);
				this.$toast.success("Datei erfolgreich hochgeladen.");
				this.metaItemsFetching.add(result?.data?.map((e) => e.id));
				this.$nextTick(async () => {
					const fetchedAssets = await this.loadMetaData(
						result?.data?.map((e) => e.id),
					);
					if (this.multiselect) {
						if (fetchedAssets?.length > 0) {
							const remainingSlots = Math.max(
								0,
								this.config.max_files - this.assets.length,
							);

							this.assets.push(...fetchedAssets.slice(0, remainingSlots));
						}
					} else {
						this.assets = fetchedAssets.slice(0, 1);
						this.sendUpdate();
					}
					this.metaItemsFetching.difference(result?.data?.map((e) => e.id));
				});
			};

			const errorCallback = (err) => {
				this.uploading = false;
				this.$progress.complete("upload" + this._uid);
				this.$toast.error(err.response.data.message);
				this.$refs.upload.value = null;
			};

			fairuUpload({
				files,
				folder: this.config.folder ?? null,
				onUploadProgressCallback: (progressEvent) => {
					this.percentUploaded = Math.round(
						(progressEvent.loaded * 100) / progressEvent.total,
					);
				},
				successCallback,
				errorCallback,
			});
		},
		sendUpdate() {
			this.update(this.assets?.map((e) => e.id));
		},
		async loadMetaData(ids) {
			if (!ids && !this.assets) {
				this.loading = false;
				return [];
			}

			const assetIds = Array.isArray(ids) ? ids : [ids].filter(Boolean);

			if (assetIds.length === 0) return [];

			this.loading = true;

			try {
				return axios
					.post("/fairu/files/list", { ids: assetIds })
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
				console.error("Error in loadMetaData:", error);
				return [];
			} finally {
				this.loading = false;
			}
		},
	},

	computed: {
		canBrowse() {
			const hasPermission =
				this.can("configure asset containers") ||
				this.can("view " + this.container + " assets");

			if (!hasPermission) return false;

			return !this.hasPendingDynamicFolder;
		},

		canUpload() {
			const hasPermission =
				this.can("configure asset containers") ||
				this.can("upload " + this.container + " assets");

			const allow = hasPermission && this.config.allow_uploads;

			return allow;
		},
	},
	async mounted() {
		this.multiselect = this.config.max_files !== 1;
		this.assets = await this.loadMetaData(this.value);
	},
	beforeDestroy() {},
};
</script>
