<script setup>
import { ref, onMounted } from 'vue';
import { Fieldtype } from '@statamic/cms';
import FairuBrowser from '../FairuBrowser.vue';
import { fairuGetFolder } from '../../utils/fetches';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

const folder = ref(null);
const searchOpen = ref(false);
const loading = ref(true);

function handleSelected(selectedFolder) {
    folder.value = selectedFolder;
    update(selectedFolder?.id);
}

onMounted(async () => {
    if (props.value) {
        await fairuGetFolder({
            folder: props.value,
            successCallback: (res) => {
                folder.value = res?.data?.entry;
            },
        });
    }
    loading.value = false;
});
</script>

<template>
    <div class="grid grid-cols-[1fr,auto]">
        <svg v-if="loading" class="animate-spin size-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <div v-else>
            <div class="text-sm" v-if="folder">{{ folder?.name }}</div>
            <button
                class="text-sm text-blue"
                @click="searchOpen = true"
                v-text="
                    !!folder ? __('fairu::folderselect.change_folder') : __('fairu::folderselect.select_folder')
                "></button>
        </div>
        <div>
            <button @click="handleSelected(null)">
                <i class="text-lg material-symbols-outlined pointer-events-none dark:!text-white dark:hover:!text-blue-500">close</i>
            </button>
        </div>
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="false"
            :initialAssets="[]"
            :meta="meta"
            selectionType="folder"
            :config="config"
            :canUpload="true" />
    </div>
</template>
