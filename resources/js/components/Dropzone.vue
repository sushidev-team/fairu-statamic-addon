<template>
    <div
        @dragenter="dragenter"
        @dragover="dragover"
        @dragleave="dragleave"
        @drop="drop">
        <div
            class="relative fa-size-full"
            :class="{ 'pointer-events-none': dragging }">
            <!-- <input
                ref="nativeFileField"
                type="file"
                multiple
                class="hidden" /> -->
            <div
                class="absolute inset-0 z-10 rounded pointer-events-none fa-flex fa-place-items-center fa-justify-center fa-bg-zinc-100 fa-transition-opacity fa-duration-150 dark:fa-bg-zinc-900"
                :class="dragging ? 'fa-opacity-90' : 'fa-opacity-0'"
                ><i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i>
                <span>Drop files here</span>
            </div>
            <div class="relative fa-size-full">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useTemplateRef, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    enabled: Boolean,
    extraData: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['updated', 'dropped']); // ✅ Fixed

const nativeFileField = useTemplateRef('nativeFileField');
const dragging = ref(false);
const uploads = ref([]);

const activeUploads = computed(() => {
    return uploads.value.filter((u) => u.instance.state === 'started');
});

const browse = () => {
    nativeFileField.value.click();
};

const addNativeFileFieldSelections = (e) => {
    for (let i = 0; i < e.target.files.length; i++) {
        addFile(e.target.files[i]);
    }
};

const dragenter = (e) => {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
    dragging.value = true;
};

const dragover = (e) => {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
};

const dragleave = (e) => {
    if (!props.enabled) return;
    if (e.target !== e.currentTarget) return;

    dragging.value = false;
};

const drop = (e) => {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
    dragging.value = false;

    const { files } = e.dataTransfer;

    emit('dropped', files); // ✅ Now works
};

const addFile = (file, data = {}) => {
    if (!props.enabled) return;

    const id = crypto.randomUUID(); // ✅ Fixed
    const upload = makeUpload(id, file, data);

    uploads.value.push({
        id,
        basename: file.name,
        extension: file.name.split('.').pop(),
        percent: 0,
        errorMessage: null,
        errorStatus: null,
        instance: upload,
        retry: (opts) => retry(id, opts),
    });
};

const makeUpload = (id, file, data) => {
    return { state: 'started' };
};

const retry = (id, opts) => {
    // Implementation needed
};

onMounted(() => {
    console.log('Dropzone mounted');
    if (nativeFileField.value) {
        nativeFileField.value.addEventListener('change', addNativeFileFieldSelections);
    }
});

onBeforeUnmount(() => {
    if (nativeFileField.value) {
        nativeFileField.value.removeEventListener('change', addNativeFileFieldSelections);
    }
});

watch(
    uploads,
    (newUploads) => {
        emit('updated', newUploads); // ✅ Now works
        processUploadQueue();
    },
    { deep: true },
);

watch(dragging, () => {
    // Empty watcher as in original
});

const processUploadQueue = () => {
    // Implementation needed
};
</script>
