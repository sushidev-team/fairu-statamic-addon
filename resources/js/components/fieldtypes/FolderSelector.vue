<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Icon } from '@statamic/cms/ui';
import FairuBrowser from '../FairuBrowser.vue';
import { fairuGetFolder } from '../../utils/fetches';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

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

function clearFolder() {
    handleSelected(null);
}

const browserConfig = computed(() => ({
    ...props.config,
    folder: folder.value?.id ?? null,
}));

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
    <div class="relative flex flex-col w-full">
        <fairu-browser
            v-if="searchOpen"
            @close="searchOpen = false"
            @selected="handleSelected"
            :multiselect="false"
            :initialAssets="[]"
            :meta="meta"
            selectionType="folder"
            :config="browserConfig"
            :canUpload="true" />

        <!-- Loading -->
        <div
            v-if="loading"
            class="flex items-center gap-2 p-3 border border-gray-300 dark:border-gray-700 rounded-xl">
            <svg class="animate-spin size-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
        </div>

        <!-- No folder selected -->
        <div
            v-else-if="!folder"
            class="p-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-850 rounded-xl flex flex-row items-center gap-3">
            <Button
                icon="folder-open"
                :text="__('fairu::folderselect.select_folder')"
                @click="searchOpen = true" />
        </div>

        <!-- Folder selected -->
        <div
            v-else
            class="flex items-center gap-2 sm:gap-3 p-3 border border-gray-300 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900">
            <Icon name="folder" class="size-5 text-gray-500 shrink-0" />
            <span class="truncate text-sm text-gray-600 dark:text-gray-400 grow min-w-0">{{ folder.name }}</span>
            <div class="flex shrink-0 items-center gap-1">
                <Button
                    icon="folder-open"
                    variant="ghost"
                    size="xs"
                    round
                    :title="__('fairu::folderselect.change_folder')"
                    @click="searchOpen = true" />
                <Button
                    icon="x"
                    variant="ghost"
                    size="xs"
                    round
                    @click="clearFolder" />
            </div>
        </div>
    </div>
</template>
