<template>
    <button
        v-if="asset?.type === 'folder' || custom"
        :class="classes.root"
        @click="handleClick">
        <i :class="classes.icon">folder</i>
        {{ name ?? asset?.name }}
    </button>
</template>

<script>
const displayTypeStyles = {
    list: {
        root: 'flex items-center w-full gap-1 px-2 py-1 text-sm fa-min-h-12 last:fa-border-b-none fa-border-b fa-border-slate-100 hover:fa-bg-gray-50 dark:fa-border-zinc-700 dark:hover:fa-bg-zinc-700',
        icon: 'text-gray-700 material-symbols-outlined fa-px-1 fa-text-2xl',
    },
    tiles: {
        root: 'fa-size-full flex fa-flex-col justify-center text-center fa-min-h-48 fa-aspect-square border rounded-lg',
        icon: 'text-gray-700 material-symbols-outlined fa-text-6xl',
    },
};
export default {
    components: {},

    data() {
        return {};
    },
    props: {
        asset: null,
        custom: Boolean,
        name: String,
        displayType: String,
    },
    methods: {
        handleClick() {
            this.$emit('click', this.asset);
        },
    },

    computed: {
        classes() {
            return displayTypeStyles[this.displayType ?? 'list'];
        },
    },

    mounted() {},
};
</script>
