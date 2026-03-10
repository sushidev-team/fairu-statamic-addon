<script setup>
import { computed } from 'vue';

const displayTypeStyles = {
    list: {
        root: 'flex items-center w-full gap-1 px-2 py-1 text-sm min-h-12 last:border-b-none border-b border-slate-100 hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-700',
        icon: 'text-gray-700 material-symbols-outlined px-1 text-2xl',
    },
    tiles: {
        root: 'size-full flex flex-col justify-center text-center min-h-48 aspect-square border rounded-lg',
        icon: 'text-gray-700 material-symbols-outlined text-6xl',
    },
};

const props = defineProps({
    asset: Object,
    custom: Boolean,
    name: String,
    displayType: String,
});

const emit = defineEmits(['click']);

const classes = computed(() => displayTypeStyles[props.displayType ?? 'list']);

function handleClick() {
    emit('click', props.asset);
}
</script>

<template>
    <button
        v-if="asset?.type === 'folder' || custom"
        :class="classes.root"
        @click="handleClick">
        <i :class="classes.icon">folder</i>
        {{ name ?? asset?.name }}
    </button>
</template>
