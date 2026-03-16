<script setup>
import { computed } from 'vue';
import { Icon } from '@statamic/cms/ui';

const props = defineProps({
    asset: Object,
    custom: Boolean,
    name: String,
    displayType: String,
});

const emit = defineEmits(['click']);

const isList = computed(() => (props.displayType ?? 'list') === 'list');

function handleClick() {
    emit('click', props.asset);
}
</script>

<template>
    <button
        type="button"
        v-if="asset?.type === 'folder' || custom"
        @click="handleClick"
        :class="isList
            ? 'flex items-center w-full gap-2 sm:gap-3 p-3 text-sm bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-900 border-b dark:border-gray-600 last:border-b-0 cursor-pointer'
            : 'size-full flex flex-col items-center justify-center text-center min-h-48 aspect-square border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer'">
        <Icon name="folder" :class="isList ? 'size-5 text-gray-500' : 'size-12 text-gray-400'" />
        <span :class="isList ? 'text-sm text-gray-600 dark:text-gray-400' : 'text-sm text-gray-600 mt-2'">
            {{ name ?? asset?.name }}
        </span>
    </button>
</template>
