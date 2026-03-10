<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    enabled: Boolean,
});

const emit = defineEmits(['dropped']);

const dragging = ref(false);
const nativeFileField = ref(null);

function addNativeFileFieldSelections(e) {
    const files = e.target.files;
    if (files?.length) {
        emit('dropped', files);
    }
}

function dragenter(e) {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
    dragging.value = true;
}

function dragover(e) {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
}

function dragleave(e) {
    if (!props.enabled) return;
    if (e.target !== e.currentTarget) return;
    dragging.value = false;
}

function drop(e) {
    if (!props.enabled) return;
    e.stopPropagation();
    e.preventDefault();
    dragging.value = false;
    emit('dropped', e.dataTransfer.files);
}

onMounted(() => {
    nativeFileField.value?.addEventListener('change', addNativeFileFieldSelections);
});

onBeforeUnmount(() => {
    nativeFileField.value?.removeEventListener('change', addNativeFileFieldSelections);
});
</script>

<template>
    <div
        @dragenter="dragenter"
        @dragover="dragover"
        @dragleave="dragleave"
        @drop="drop">
        <div
            class="relative size-full"
            :class="{ 'pointer-events-none': dragging }">
            <input
                type="file"
                multiple
                class="hidden"
                ref="nativeFileField" />
            <div
                class="absolute inset-0 z-10 rounded pointer-events-none flex place-items-center justify-center bg-zinc-100 transition-opacity duration-150 dark:bg-zinc-900"
                :class="dragging ? 'opacity-90' : 'opacity-0'">
                <i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i>
                <span>Drop files here</span>
            </div>
            <div class="relative size-full">
                <slot></slot>
            </div>
        </div>
    </div>
</template>
