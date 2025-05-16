<template>
    <div
        :class="[classes.root, { 'fa-opacity-50': disabled }]"
        @click="toggleSelection"
        v-if="asset.type !== 'folder'">
        <div class="flex items-center gap-1 cursor-pointer grow fa-size-full">
            <input-checkbox
                v-if="multiselect"
                :class="classes.checkbox"
                :id="asset?.id"
                :checked="selected"
                :disabled="disabled" />
            <div :class="classes.imageWrapper">
                <img
                    v-if="
                        meta.proxy &&
                        asset?.blocked != true &&
                        (asset?.mime?.startsWith('image/') || asset?.mime?.startsWith('video/'))
                    "
                    draggable="false"
                    :src="`${meta.proxy}/${asset.id}/thumbnail.webp?width=${imageSize}&height=${imageSize}`"
                    :class="classes.image" />
                <div
                    class="items-end content-center fa-grid fa-size-full fa-justify-center fa-p-8 fa-text-center fa-text-gray-600"
                    style="font-size: 8px"
                    v-if="!asset?.mime?.startsWith('image/') && !asset?.mime?.startsWith('video/')">
                    <i
                        class="material-symbols-outlined fa-pointer-events-none fa-text-[150px] fa-text-gray-900 dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500"
                        >description</i
                    >
                    <div class="fa-text-base fa-text-gray-800">{{ asset?.name }}</div>
                </div>
            </div>
            <div
                class="flex items-center gap-2 text-sm grow"
                v-if="displayType !== 'tiles'">
                {{ asset.name }}
            </div>
        </div>
        <div :class="classes.actions">
            <button
                @click.stop="emitPreview"
                title="Open in preview"
                ><i :class="classes.action">visibility</i></button
            >
            <a
                @click.stop
                :href="meta.file + '/' + asset.id"
                target="_blank"
                :title="__('fairu::browser.edit_in_fairu')"
                class="flex gap-1 text-xs cursor-pointer"
                ><i :class="classes.action">open_in_new</i>
            </a>
        </div>
    </div>
</template>

<script>
const displayTypeStyles = {
    list: {
        root: 'grid items-center gap-2 px-2 py-1 fa-min-h-12 fa-select-none fa-grid-cols-[1fr,auto] last:fa-border-b-none fa-border-b fa-border-slate-100 hover:fa-bg-gray-50 dark:fa-border-zinc-700 dark:hover:fa-bg-zinc-700',
        imageWrapper: 'flex items-center justify-center flex-none overflow-hidden bg-gray-300 rounded-full fa-size-8',
        image: 'object-cover',
        checkbox: 'fa-mr-1.5',
        actions: 'flex gap-1',
        action: 'text-lg text-gray-300 material-symbols-outlined fa-pointer-events-none dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500',
    },
    tiles: {
        root: 'fa-size-full relative fa-min-h-48 fa-aspect-square fa-group',
        imageWrapper: 'fa-rounded-lg overflow-hidden fa-size-full fa-bg-gray-100',
        image: 'fa-size-full object-cover',
        checkbox: 'fa-m-4 absolute z-10 left-0 top-0',
        actions:
            'fa-flex fa-w-auto fa-absolute fa-right-2 fa-bottom-2 fa-justify-end fa-px-1 fa-bg-gray-900/0 fa-rounded-xl group-hover:fa-bg-gray-900/30 fa-transition-all fa-duration-300',
        action: 'fa-text-xl fa-p-1.5 text-gray-300 material-symbols-outlined fa-pointer-events-none dark:fa-text-gray-600 dark:hover:fa-text-blue-500 fa-opacity-60 group-hover:fa-opacity-100 fa-transition-opacity fa-duration-300',
    },
};

export default {
    components: {},

    data() {
        return {};
    },
    props: {
        asset: null,
        meta: null,
        displayType: String,
        selected: Boolean,
        multiselect: Boolean,
        disabled: Boolean,
    },
    methods: {
        toggleSelection() {
            if (this.disabled) return;
            this.$emit('change', { asset: this.asset, selected: !this.selected });
        },
        getExtension(mime) {
            const parts = mime.split('/');
            if (parts.length == 2) {
                return parts[1];
            }
            return 'n/a';
        },
        emitPreview() {
            this.$emit('preview');
        },
    },

    computed: {
        classes() {
            return displayTypeStyles[this.displayType ?? 'list'];
        },
        imageSize() {
            return this.displayType === 'tiles' ? 350 : 34;
        },
    },

    mounted() {},
};
</script>
