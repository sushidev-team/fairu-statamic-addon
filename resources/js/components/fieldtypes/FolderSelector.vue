<template>
    <div class="fa-grid fa-grid-cols-[1fr,auto]">
        <div v-if="loading.value">Loading</div>

        <div v-else>
            <div
                v-if="folder"
                class="text-sm"
                >{{ folder?.name }}</div
            >
            <Button
                class="text-sm text-blue"
                @click="searchOpen = true"
                v-text="
                    !!folder ? __('fairu::folderselect.change_folder') : __('fairu::folderselect.select_folder')
                "></Button>
        </div>
        <div>
            <Button @click="handleSelected(null)"
                ><i
                    class="text-lg material-symbols-outlined fa-pointer-events-none dark:!fa-text-white dark:hover:!fa-text-blue-500"
                    >close</i
                ></Button
            >
        </div>
        <fairu-browser
            v-if="searchOpen"
            :multiselect="multiselect"
            :initial-assets="assets"
            :meta="meta"
            selection-type="folder"
            :config="config"
            @close="searchOpen = false"
            @selected="handleSelected" />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import FairuBrowser from '../FairuBrowser.vue';
import { fairuGetFolder } from '../../utils/fetches';
import { Fieldtype } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

// const props = defineProps({
//     value: String,
//     initialFolder: String,
//     meta: Object,
//     config: Object,
//     multiselect: {
//         type: Boolean,
//         default: false
//     },
//     assets: {
//         type: Array,
//         default: () => []
//     }
// })

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use(emit, props);
defineExpose(expose);

const folder = ref(null);
const searchOpen = ref(false);
const loading = ref(true);

const handleSelected = (selectedFolder) => {
    folder.value = selectedFolder;
    emit('update', selectedFolder?.id);
};

onMounted(async () => {
    if (props.value) {
        await fairuGetFolder({
            folder: props.value,
            successCallback: (res) => {
                folder.value = res?.data?.entry;
                console.log('Success');
            },
        });
    }
    loading.value = false;
    console.log({ loading: loading.value });
});
</script>
