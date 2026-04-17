<script setup>
import { ref, computed, onMounted, onBeforeUnmount, getCurrentInstance, defineComponent, h } from 'vue';
import { toast } from '@statamic/cms/api';
import { Stack, Button, Input, Textarea, Field, Heading, Subheading, Description, Dropdown, DropdownMenu, DropdownItem, DropdownSeparator } from '@statamic/cms/ui';
import FairuAssetActions from './FairuAssetActions.vue';
import { fairuGetFile, fairuUpdateFile } from '../utils/fetches';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const FairuStackHeader = defineComponent({
    name: 'StackHeader',
    setup(_, { slots }) {
        return () => h('div', {
            class: 'flex items-center justify-between rounded-t-xl border-b border-gray-300 ps-6 pe-4 py-2 dark:border-gray-950 dark:bg-gray-800',
        }, slots.default?.());
    },
});

const props = defineProps({
    assetId: { type: String, required: true },
    meta: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved', 'renamed', 'moved', 'deleted']);

const actionsRef = ref(null);

function openRename() {
    if (asset.value) actionsRef.value?.openRename(asset.value);
}

function openMove() {
    if (asset.value) actionsRef.value?.openMove(asset.value);
}

function openDelete() {
    if (asset.value) actionsRef.value?.openDelete(asset.value);
}

function handleRenamed(payload) {
    if (asset.value?.id === payload.id) {
        asset.value = { ...asset.value, name: payload.name };
    }
    emit('renamed', payload);
}

function handleMoved(payload) {
    emit('moved', payload);
    emit('close');
}

function handleDeleted(payload) {
    emit('deleted', payload);
    emit('close');
}

const loading = ref(true);
const saving = ref(false);
const asset = ref(null);

const alt = ref('');
const caption = ref('');
const description = ref('');
const focalX = ref(50);
const focalY = ref(50);

const pickerEl = ref(null);
const imageEl = ref(null);
const dragging = ref(false);
const naturalRatio = ref(1);
const infoExpanded = ref(false);

const isImage = computed(() => asset.value?.mime?.startsWith('image/'));

const formattedSize = computed(() => {
    const size = asset.value?.size;
    if (!size) return null;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / 1024 / 1024).toFixed(2)} MB`;
});

const formattedDimensions = computed(() => {
    const w = asset.value?.original_width || asset.value?.width;
    const h = asset.value?.original_height || asset.value?.height;
    if (!w || !h) return null;
    return `${w} × ${h} px`;
});

const formattedCoordinates = computed(() => `${Math.round(focalX.value)}% · ${Math.round(focalY.value)}%`);

const imageUrl = computed(() => {
    if (!asset.value?.id) return null;
    return `${props.meta.proxy}/${asset.value.id}/thumbnail.webp?width=1280`;
});

const focalCss = computed(() => `${focalX.value}% ${focalY.value}%`);

function parseFocalPoint(str) {
    if (!str || typeof str !== 'string') return { x: 50, y: 50 };
    const parts = str.split('-');
    const x = parseFloat(parts[0]);
    const y = parseFloat(parts[1]);
    return {
        x: Number.isFinite(x) ? clamp(x, 0, 100) : 50,
        y: Number.isFinite(y) ? clamp(y, 0, 100) : 50,
    };
}

function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
}

function updateFocalFromEvent(evt) {
    if (!pickerEl.value) return;
    const rect = pickerEl.value.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    const x = ((evt.clientX - rect.left) / rect.width) * 100;
    const y = ((evt.clientY - rect.top) / rect.height) * 100;
    focalX.value = Math.round(clamp(x, 0, 100) * 10) / 10;
    focalY.value = Math.round(clamp(y, 0, 100) * 10) / 10;
}

function handlePointerDown(evt) {
    if (!isImage.value) return;
    dragging.value = true;
    updateFocalFromEvent(evt);
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp, { once: true });
}

function handlePointerMove(evt) {
    if (!dragging.value) return;
    updateFocalFromEvent(evt);
}

function handlePointerUp() {
    dragging.value = false;
    window.removeEventListener('pointermove', handlePointerMove);
}

function resetFocal() {
    focalX.value = 50;
    focalY.value = 50;
}

function focalToString() {
    if (focalX.value === 50 && focalY.value === 50) return null;
    return `${Math.round(focalX.value * 10) / 10}-${Math.round(focalY.value * 10) / 10}-1`;
}

async function load() {
    loading.value = true;
    try {
        const data = await fairuGetFile(props.assetId);
        const file = data?.data ?? data;
        asset.value = file;
        alt.value = file?.alt || '';
        caption.value = file?.caption || '';
        description.value = file?.description || '';
        const fp = parseFocalPoint(file?.focal_point);
        focalX.value = fp.x;
        focalY.value = fp.y;
    } catch (err) {
        console.error(err);
        toast.error(__('fairu::fieldtype.editor.load_error'));
        emit('close');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const payload = {
            alt: alt.value ?? '',
            caption: caption.value ?? '',
            description: description.value ?? '',
            focal_point: focalToString() ?? '',
        };
        const result = await fairuUpdateFile(props.assetId, payload);
        toast.success(__('fairu::fieldtype.editor.save_success'));
        emit('saved', result?.data ?? result);
        emit('close');
    } catch (err) {
        console.error(err);
        toast.error(err?.message || __('fairu::fieldtype.editor.save_error'));
    } finally {
        saving.value = false;
    }
}

function handleImageLoad(evt) {
    const img = evt.target;
    if (img.naturalWidth && img.naturalHeight) {
        naturalRatio.value = img.naturalWidth / img.naturalHeight;
    }
}

onMounted(() => {
    load();
});

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', handlePointerMove);
});
</script>

<template>
    <FairuAssetActions
        ref="actionsRef"
        :meta="meta"
        @renamed="handleRenamed"
        @moved="handleMoved"
        @deleted="handleDeleted" />
    <Stack open @closed="emit('close')" inset :wrap-slot="false" size="xl">
        <FairuStackHeader>
            <div class="flex items-center gap-3 min-w-0">
                <a href="https://fairu.app" target="_blank" class="flex items-center shrink-0">
                    <img class="w-16 h-auto" src="../../svg/fairu-logo.svg" alt="Fairu" />
                </a>
                <div class="truncate text-sm text-gray-600 dark:text-gray-300">
                    {{ asset?.name || __('fairu::fieldtype.editor.title') }}
                </div>
            </div>
            <div class="flex items-center gap-1">
                <Dropdown v-if="asset" placement="bottom-end">
                    <template #trigger>
                        <Button icon="dots-vertical" variant="ghost" size="sm" />
                    </template>
                    <DropdownMenu>
                        <DropdownItem
                            :text="__('fairu::fieldtype.rename')"
                            icon="rename"
                            @click="openRename" />
                        <DropdownItem
                            :text="__('fairu::fieldtype.move')"
                            icon="folder-open"
                            @click="openMove" />
                        <DropdownSeparator />
                        <DropdownItem
                            :text="__('fairu::browser.edit_in_fairu')"
                            icon="external-link"
                            :href="meta.file + '/' + asset.id"
                            target="_blank" />
                        <DropdownSeparator />
                        <DropdownItem
                            :text="__('fairu::fieldtype.delete')"
                            icon="trash"
                            variant="destructive"
                            class="!text-red-600 dark:!text-red-400"
                            @click="openDelete" />
                    </DropdownMenu>
                </Dropdown>
                <Button icon="x" variant="ghost" class="-me-2" @click="emit('close')" />
            </div>
        </FairuStackHeader>

        <div class="flex-1 overflow-y-auto">
            <div v-if="loading" class="grid items-center justify-center w-full h-full p-16">
                <svg class="animate-spin size-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>

            <div v-else class="p-6 grid gap-6 lg:grid-cols-2 lg:items-start">
                <!-- Metadata -->
                <section class="space-y-3">
                    <header>
                        <Heading>{{ __('fairu::fieldtype.editor.title') }}</Heading>
                        <Description>{{ __('fairu::fieldtype.editor.description') }}</Description>
                    </header>

                    <Field
                        :label="__('fairu::fieldtype.editor.alt')"
                        :instructions="__('fairu::fieldtype.editor.alt_instructions')">
                        <Input v-model="alt" />
                    </Field>
                    <Field
                        :label="__('fairu::fieldtype.editor.caption')"
                        :instructions="__('fairu::fieldtype.editor.caption_instructions')">
                        <Input v-model="caption" />
                    </Field>
                    <Field
                        :label="__('fairu::fieldtype.editor.long_description')"
                        :instructions="__('fairu::fieldtype.editor.long_description_instructions')">
                        <Textarea v-model="description" elastic :rows="4" />
                    </Field>
                </section>

                <!-- Focal point -->
                <section v-if="isImage" class="space-y-3">
                    <header class="flex items-start justify-between gap-4">
                        <div>
                            <Subheading>{{ __('fairu::fieldtype.editor.focal_point_title') }}</Subheading>
                            <Description>{{ __('fairu::fieldtype.editor.focal_point_description') }}</Description>
                        </div>
                        <Button
                            size="xs"
                            variant="ghost"
                            icon="rotate-counter-clockwise"
                            :text="__('fairu::fieldtype.editor.focal_point_reset')"
                            @click="resetFocal" />
                    </header>

                    <!-- Picker + info side-by-side -->
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] items-start">
                        <div class="relative flex justify-center bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                            <div
                                ref="pickerEl"
                                class="relative rounded-md overflow-hidden bg-gray-200 dark:bg-gray-900 select-none touch-none inline-flex max-w-full"
                                :class="{ 'cursor-grabbing': dragging, 'cursor-crosshair': !dragging }"
                                @pointerdown.prevent="handlePointerDown">
                                <img
                                    ref="imageEl"
                                    :src="imageUrl"
                                    :alt="asset?.name"
                                    draggable="false"
                                    class="block max-h-[38vh] max-w-full w-auto h-auto pointer-events-none"
                                    @load="handleImageLoad" />
                                <div
                                    class="absolute size-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-lg bg-white/40 backdrop-blur-sm pointer-events-none ring-2 ring-black/30"
                                    :style="{ left: focalX + '%', top: focalY + '%' }"></div>
                            </div>
                            <!-- Info toggle on mobile -->
                            <Button
                                class="md:hidden absolute top-2 right-2"
                                size="xs"
                                variant="ghost"
                                :icon="infoExpanded ? 'x' : 'information'"
                                round
                                :title="__('fairu::fieldtype.editor.info_dimensions')"
                                @click="infoExpanded = !infoExpanded" />
                        </div>
                        <!-- Info panel: always visible on md+, toggle on mobile -->
                        <dl
                            class="text-xs min-w-36 space-y-2 text-gray-700 dark:text-gray-300"
                            :class="infoExpanded ? 'block' : 'hidden md:block'">
                            <div v-if="formattedDimensions">
                                <dt class="uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('fairu::fieldtype.editor.info_dimensions') }}
                                </dt>
                                <dd class="font-mono">{{ formattedDimensions }}</dd>
                            </div>
                            <div v-if="formattedSize">
                                <dt class="uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('fairu::fieldtype.editor.info_size') }}
                                </dt>
                                <dd class="font-mono">{{ formattedSize }}</dd>
                            </div>
                            <div v-if="asset?.mime">
                                <dt class="uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('fairu::fieldtype.editor.info_type') }}
                                </dt>
                                <dd class="font-mono">{{ asset.mime }}</dd>
                            </div>
                            <div>
                                <dt class="uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('fairu::fieldtype.editor.info_coordinates') }}
                                </dt>
                                <dd class="font-mono">{{ formattedCoordinates }}</dd>
                            </div>
                            <Description class="pt-1">{{ __('fairu::fieldtype.editor.focal_point_hint') }}</Description>
                        </dl>
                    </div>

                    <!-- Previews -->
                    <div>
                        <Subheading class="mb-2">{{ __('fairu::fieldtype.editor.preview') }}</Subheading>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="aspect-[16/9] bg-gray-100 dark:bg-gray-800 rounded overflow-hidden">
                                <img
                                    v-if="imageUrl"
                                    :src="imageUrl"
                                    class="size-full object-cover"
                                    :style="{ objectPosition: focalCss }"
                                    draggable="false" />
                            </div>
                            <div class="aspect-square bg-gray-100 dark:bg-gray-800 rounded overflow-hidden">
                                <img
                                    v-if="imageUrl"
                                    :src="imageUrl"
                                    class="size-full object-cover"
                                    :style="{ objectPosition: focalCss }"
                                    draggable="false" />
                            </div>
                            <div class="aspect-[3/4] bg-gray-100 dark:bg-gray-800 rounded overflow-hidden">
                                <img
                                    v-if="imageUrl"
                                    :src="imageUrl"
                                    class="size-full object-cover"
                                    :style="{ objectPosition: focalCss }"
                                    draggable="false" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <template #footer-end>
            <Button :text="__('fairu::browser.cancel')" :disabled="saving" @click="emit('close')" />
            <Button
                variant="primary"
                :text="__('fairu::fieldtype.editor.save')"
                :loading="saving"
                :disabled="loading"
                @click="save" />
        </template>
    </Stack>
</template>
