<script setup>
import { computed, getCurrentInstance } from 'vue';
import { Checkbox, Button, Dropdown, DropdownMenu, DropdownItem } from '@statamic/cms/ui';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const props = defineProps({
    asset: Object,
    meta: Object,
    displayType: String,
    selected: Boolean,
    multiselect: Boolean,
    disabled: Boolean,
});

const emit = defineEmits(['change', 'preview', 'edit']);

function toggleSelection() {
    if (props.disabled) return;
    emit('change', { asset: props.asset, selected: !props.selected });
}

function emitPreview() {
    emit('preview');
}

function emitEdit() {
    emit('edit', props.asset);
}

const isList = computed(() => (props.displayType ?? 'list') === 'list');
const imageSize = computed(() => isList.value ? 34 : 350);
const isMedia = computed(() =>
    props.asset?.mime?.startsWith('image/') || props.asset?.mime?.startsWith('video/')
);
</script>

<template>
    <div v-if="asset.type !== 'folder'">
        <!-- LIST MODE: Statamic Listing-style table row -->
        <div
            v-if="isList"
            :class="['group relative flex items-center gap-2 sm:gap-3 p-3 bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-900 border-b dark:border-gray-600 last:border-b-0 cursor-pointer', { 'opacity-50': disabled }]"
            @click="toggleSelection">
            <Checkbox
                v-if="multiselect"
                solo
                size="sm"
                :model-value="selected"
                :disabled="disabled" />
            <div class="shrink-0 size-7 rounded-sm overflow-hidden bg-gray-100 flex items-center justify-center">
                <img
                    v-if="meta.proxy && asset?.blocked !== true && isMedia"
                    draggable="false"
                    :src="`${meta.proxy}/${asset.id}/thumbnail.webp?width=${imageSize}&height=${imageSize}`"
                    class="size-full object-cover" />
                <i
                    v-else
                    class="material-symbols-outlined text-gray-400 dark:text-gray-600 text-base">description</i>
            </div>
            <span class="truncate text-sm text-gray-600 dark:text-gray-400 grow min-w-0">{{ asset.name }}</span>
            <!-- Row actions (Listing-style) -->
            <div class="flex shrink-0 items-center gap-0.5 pl-2">
                <Button
                    icon="eye"
                    variant="ghost"
                    size="xs"
                    round
                    :title="__('fairu::browser.preview')"
                    @click.stop="emitPreview" />
                <Button
                    icon="pencil"
                    variant="ghost"
                    size="xs"
                    round
                    :title="__('fairu::fieldtype.edit')"
                    @click.stop="emitEdit" />
                <Dropdown placement="left-start">
                    <template #trigger>
                        <Button
                            icon="dots-vertical"
                            variant="ghost"
                            size="xs"
                            round />
                    </template>
                    <DropdownMenu>
                        <DropdownItem
                            :text="__('fairu::browser.preview')"
                            icon="eye"
                            @click.stop="emitPreview" />
                        <DropdownItem
                            :text="__('fairu::fieldtype.edit')"
                            icon="pencil"
                            @click.stop="emitEdit" />
                        <DropdownItem
                            :text="__('fairu::browser.edit_in_fairu')"
                            icon="external-link"
                            :href="meta.file + '/' + asset.id"
                            target="_blank"
                            @click.stop />
                    </DropdownMenu>
                </Dropdown>
            </div>
        </div>

        <!-- TILES MODE -->
        <div
            v-else
            :class="['size-full relative min-h-48 aspect-square group', { 'opacity-50': disabled }]"
            @click="toggleSelection">
            <Checkbox
                v-if="multiselect"
                solo
                size="sm"
                class="m-4 absolute z-10 left-0 top-0"
                :model-value="selected"
                :disabled="disabled" />
            <div class="rounded-lg overflow-hidden size-full bg-gray-100">
                <img
                    v-if="meta.proxy && asset?.blocked !== true && isMedia"
                    draggable="false"
                    :src="`${meta.proxy}/${asset.id}/thumbnail.webp?width=${imageSize}&height=${imageSize}`"
                    class="size-full object-cover" />
                <div
                    v-else
                    class="grid size-full place-items-center p-8 text-center text-gray-600">
                    <i class="material-symbols-outlined text-gray-400 dark:text-gray-600" style="font-size: 80px">description</i>
                    <div class="text-sm text-gray-600 mt-2 truncate w-full">{{ asset.name }}</div>
                </div>
            </div>
            <!-- Tile actions overlay -->
            <div class="flex w-auto absolute right-2 bottom-2 justify-end gap-0.5 rounded-xl bg-gray-900/0 group-hover:bg-gray-900/30 transition-all duration-300">
                <Button
                    icon="eye"
                    variant="ghost"
                    size="xs"
                    round
                    class="opacity-60 group-hover:opacity-100 transition-opacity duration-300 text-white"
                    @click.stop="emitPreview" />
                <Button
                    icon="pencil"
                    variant="ghost"
                    size="xs"
                    round
                    class="opacity-60 group-hover:opacity-100 transition-opacity duration-300 text-white"
                    :title="__('fairu::fieldtype.edit')"
                    @click.stop="emitEdit" />
                <Button
                    as="a"
                    :href="meta.file + '/' + asset.id"
                    target="_blank"
                    icon="external-link"
                    variant="ghost"
                    size="xs"
                    round
                    class="opacity-60 group-hover:opacity-100 transition-opacity duration-300 text-white"
                    @click.stop />
            </div>
        </div>
    </div>
</template>
