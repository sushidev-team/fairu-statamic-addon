<template>
    <div class="fa-grid fa-grid-cols-[1fr,auto]">
        <ring-loader
            color="#4a4a4a"
            class="w-5 h-5"
            size="24"
            v-if="loading" />
        <div v-else>
            <div
                class="text-sm"
                v-if="folder"
                >{{ folder?.name }}</div
            >
            <button
                class="text-sm text-blue"
                @click="searchOpen = true"
                v-text="
                    !!folder ? __('fairu::folderselect.change_folder') : __('fairu::folderselect.select_folder')
                "></button>
        </div>
        <div>
            <button @click="handleSelected(null)"
                ><i
                    class="text-lg material-symbols-outlined fa-pointer-events-none dark:!fa-text-white dark:hover:!fa-text-blue-500"
                    >close</i
                ></button
            >
        </div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="multiselect"
            :initialAssets="assets"
            :meta="meta"
            selectionType="folder"
            :config="config" />
    </div>
</template>

<script>
import FairuBrowser from '../FairuBrowser.vue';
import { fairuGetFolder } from '../../utils/fetches';

export default {
    mixins: [Fieldtype],

    components: {
        FairuBrowser,
    },
    props: {
        initialFolder: String,
    },

    data() {
        return {
            folder: null,
            searchOpen: false,
            loading: true,
        };
    },

    methods: {
        handleSelected(folder) {
            this.folder = folder;
            this.update(folder?.id);
        },
    },
    async mounted() {
        if (this.value) {
            await fairuGetFolder({
                folder: this.value,
                successCallback: (res) => {
                    this.folder = res?.data?.entry;
                },
            });
        }
        this.loading = false;
    },
};
</script>
