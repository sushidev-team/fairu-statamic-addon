<script setup>
import { computed } from 'vue';
import { Checkbox } from '@statamic/cms/ui';

const displayTypeStyles = {
    list: {
        root: 'flex items-center gap-2 px-2 py-1 min-h-12 select-none last:border-b-none border-b border-slate-100 hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-700',
        imageWrapper: 'flex items-center justify-center shrink-0 overflow-hidden bg-gray-300 rounded-full size-8',
        image: 'object-cover',
        checkbox: 'mr-1.5',
        actions: 'flex shrink-0 gap-1',
        action: 'text-lg text-gray-300 material-symbols-outlined pointer-events-none dark:!text-gray-600 dark:hover:!text-blue-500',
    },
    tiles: {
        root: 'size-full relative min-h-48 aspect-square group',
        imageWrapper: 'rounded-lg overflow-hidden size-full bg-gray-100',
        image: 'size-full object-cover',
        checkbox: 'm-4 absolute z-10 left-0 top-0',
        actions:
            'flex w-auto absolute right-2 bottom-2 justify-end px-1 bg-gray-900/0 rounded-xl group-hover:bg-gray-900/30 transition-all duration-300',
        action: 'text-xl p-1.5 text-gray-300 material-symbols-outlined pointer-events-none dark:text-gray-600 dark:hover:text-blue-500 opacity-60 group-hover:opacity-100 transition-opacity duration-300',
    },
};

const props = defineProps({
    asset: Object,
    meta: Object,
    displayType: String,
    selected: Boolean,
    multiselect: Boolean,
    disabled: Boolean,
});

const emit = defineEmits(['change', 'preview']);

function toggleSelection() {
    if (props.disabled) return;
    emit('change', { asset: props.asset, selected: !props.selected });
}

function emitPreview() {
    emit('preview');
}

const classes = computed(() => displayTypeStyles[props.displayType ?? 'list']);
const imageSize = computed(() => (props.displayType === 'tiles' ? 350 : 34));
</script>

<template>
    <div
        :class="[classes.root, { 'opacity-50': disabled }]"
        @click="toggleSelection"
        v-if="asset.type !== 'folder'">
        <div class="flex items-center gap-1 cursor-pointer grow min-w-0">
            <Checkbox
                v-if="multiselect"
                solo
                size="sm"
                :class="classes.checkbox"
                :model-value="selected"
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
                    class="items-end content-center grid size-full justify-center p-8 text-center text-gray-600"
                    style="font-size: 8px"
                    v-if="!asset?.mime?.startsWith('image/') && !asset?.mime?.startsWith('video/')">
                    <i class="material-symbols-outlined pointer-events-none text-gray-900 dark:!text-gray-600" style="font-size: 150px">description</i>
                    <div class="text-base text-gray-800">{{ asset?.name }}</div>
                </div>
            </div>
            <div
                class="flex items-center gap-2 text-sm grow min-w-0 truncate"
                v-if="displayType !== 'tiles'">
                {{ asset.name }}
            </div>
        </div>
        <div :class="classes.actions">
            <button
                @click.stop="emitPreview"
                title="Open in preview">
                <i :class="classes.action">visibility</i>
            </button>
            <a
                @click.stop
                :href="meta.file + '/' + asset.id"
                target="_blank"
                :title="__('fairu::browser.edit_in_fairu')"
                class="flex gap-1 text-xs cursor-pointer">
                <i :class="classes.action">open_in_new</i>
            </a>
        </div>
    </div>
</template>
