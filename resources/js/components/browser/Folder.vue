<script setup>
import { computed } from 'vue';

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
        <svg
            viewBox="0 0 80 66"
            fill="#3EBEB41C"
            stroke="#3EBEB4"
            stroke-width="3"
            stroke-linejoin="round"
            stroke-linecap="round"
            xmlns="http://www.w3.org/2000/svg"
            :class="isList ? 'h-5 w-[24px] shrink-0' : 'h-[66px] w-[80px]'">
            <path d="M8 6 H26 Q30 6 30 10 V14 H72 Q78 14 78 20 V56 Q78 62 72 62 H8 Q2 62 2 56 V12 Q2 6 8 6 Z"/>
        </svg>
        <span :class="isList ? 'text-sm text-gray-600 dark:text-gray-400' : 'text-sm text-gray-600 mt-2'">
            {{ name ?? asset?.name }}
        </span>
    </button>
</template>
