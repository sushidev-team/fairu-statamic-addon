<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
import { toast } from '@statamic/cms/api';
import { Button, Checkbox } from '@statamic/cms/ui';
import emblaCarouselVue from 'embla-carousel-vue';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const props = defineProps({
    items: { type: Array, required: true },
    startIndex: { type: Number, default: 0 },
    meta: { type: Object, required: true },
    multiselect: { type: Boolean, default: false },
    isFolderMode: { type: Boolean, default: false },
    isSelectedFn: { type: Function, default: null },
});

const emit = defineEmits(['close', 'edit', 'toggle-select', 'confirm-select', 'apply']);

const currentIndex = ref(props.startIndex);

const [mainRef, mainApi] = emblaCarouselVue({ loop: false });
const [thumbsRef, thumbsApi] = emblaCarouselVue({ loop: false, containScroll: 'keepSnaps', dragFree: true, align: 'center' });

const current = computed(() => props.items[currentIndex.value] ?? null);

function isMediaItem(item) {
    return item?.mime?.startsWith('image/') || item?.mime?.startsWith('video/');
}

function thumbnailUrl(item, size = 128) {
    return `${props.meta.proxy}/${item.id}/thumbnail.webp?width=${size}&height=${size}`;
}

const IMAGE_WIDTHS = [480, 768, 1024, 1280, 1600, 1920];

function fullUrl(item, width = 1600) {
    return `${props.meta.proxy}/${item.id}/thumbnail.webp?width=${width}`;
}

function fullSrcset(item) {
    return IMAGE_WIDTHS.map((w) => `${fullUrl(item, w)} ${w}w`).join(', ');
}

function scrollPrev() {
    mainApi.value?.scrollPrev();
}

function scrollNext() {
    mainApi.value?.scrollNext();
}

function selectThumb(idx) {
    mainApi.value?.scrollTo(idx);
}

function handleMainSelect() {
    const api = mainApi.value;
    if (!api) return;
    currentIndex.value = api.selectedScrollSnap();
    thumbsApi.value?.scrollTo(currentIndex.value);
}

function handleKeydown(evt) {
    if (evt.key === 'ArrowLeft') {
        scrollPrev();
        evt.preventDefault();
        evt.stopPropagation();
    } else if (evt.key === 'ArrowRight') {
        scrollNext();
        evt.preventDefault();
        evt.stopPropagation();
    } else if (evt.key === 'Escape') {
        emit('close');
        evt.preventDefault();
        evt.stopPropagation();
        // Prevent the surrounding Stack from also handling Escape and closing the whole browser.
        if (typeof evt.stopImmediatePropagation === 'function') {
            evt.stopImmediatePropagation();
        }
    } else if ((evt.key === 'Enter' || evt.key === ' ') && props.multiselect && current.value) {
        announceToggle(current.value);
        emit('toggle-select', current.value);
        evt.preventDefault();
        evt.stopPropagation();
    }
}

function wireEmbla() {
    const api = mainApi.value;
    if (!api) return;
    api.on('select', handleMainSelect);
    api.on('reInit', handleMainSelect);
    api.scrollTo(currentIndex.value, true);
    nextTick(() => thumbsApi.value?.scrollTo(currentIndex.value, true));
}

function announceToggle(item) {
    const wasSelected = props.isSelectedFn?.(item);
    const key = wasSelected ? 'fairu::browser.image_deselected' : 'fairu::browser.image_selected';
    const msg = __(key, { name: item?.name ?? '' });
    // Statamic's public toast API ships only success/info/error with fixed icons.
    // Use success for both: the action completed either way; the message describes the direction.
    toast.success(msg, { duration: 1500 });
}

function handleConfirm() {
    if (!current.value) return;
    if (props.multiselect) {
        emit('apply');
    } else {
        emit('confirm-select', current.value);
    }
}

function handleImageClick(item) {
    if (!props.multiselect || props.isFolderMode) return;
    // Don't fire click if the user just dragged to swipe
    const api = mainApi.value;
    if (api && typeof api.clickAllowed === 'function' && !api.clickAllowed()) return;
    announceToggle(item);
    emit('toggle-select', item);
}

function handleEdit() {
    if (current.value) emit('edit', current.value);
}

onMounted(() => {
    // Capture-phase listener so we win over any outer Stack handlers for Escape.
    window.addEventListener('keydown', handleKeydown, true);
    // Embla's onMounted fired before this one, so mainApi should now be set.
    if (mainApi.value) {
        wireEmbla();
    } else {
        // Fallback: wait for Embla to finish initializing if async
        const stop = watch(mainApi, (api) => {
            if (!api) return;
            wireEmbla();
            stop();
        });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown, true);
});

defineExpose({ scrollPrev, scrollNext });
</script>

<template>
    <div
        v-if="current"
        class="z-10 fixed inset-4 md:inset-8 rounded-xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 grid overflow-hidden"
        style="grid-template-rows: auto 1fr auto">
        <!-- Toolbar -->
        <div class="flex h-min w-full items-center justify-between gap-3 p-4">
            <div class="flex items-center gap-3 min-w-0">
                <Checkbox
                    v-if="multiselect && !isFolderMode && isSelectedFn"
                    solo
                    size="sm"
                    :model-value="isSelectedFn(current)"
                    @update:model-value="emit('toggle-select', current)" />
                <div class="text-lg text-gray-900 dark:text-gray-100 font-medium min-w-0 truncate">
                    {{ current.name }}
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="text-sm text-gray-600 dark:text-gray-400 tabular-nums">
                    {{ currentIndex + 1 }} / {{ items.length }}
                </div>
                <Button
                    v-if="!isFolderMode"
                    size="sm"
                    icon="pencil"
                    :text="__('fairu::fieldtype.edit')"
                    @click="handleEdit" />
                <Button
                    v-if="!isFolderMode"
                    variant="primary"
                    size="sm"
                    icon="check"
                    :text="multiselect
                        ? __('fairu::browser.apply')
                        : __('fairu::browser.select')"
                    @click="handleConfirm" />
                <Button
                    icon="x"
                    variant="ghost"
                    :title="__('fairu::browser.cancel')"
                    @click="emit('close')" />
            </div>
        </div>

        <!-- Main carousel -->
        <div class="relative min-h-0 px-4">
            <div ref="mainRef" class="embla size-full overflow-hidden">
                <div class="embla__container flex size-full">
                    <div
                        v-for="(item, index) in items"
                        :key="'main-' + item.id"
                        class="embla__slide relative flex items-center justify-center"
                        style="flex: 0 0 100%; min-width: 0">
                        <img
                            v-if="meta.proxy && item?.blocked !== true && isMediaItem(item)"
                            draggable="false"
                            :src="fullUrl(item, 1600)"
                            :srcset="fullSrcset(item)"
                            sizes="100vw"
                            loading="lazy"
                            class="max-h-full max-w-full object-contain select-none"
                            :class="multiselect && !isFolderMode ? 'cursor-pointer' : ''"
                            @click="handleImageClick(item)" />
                        <div
                            v-else
                            class="grid size-full place-items-center text-gray-600">
                            <i class="material-symbols-outlined" style="font-size: 160px">description</i>
                        </div>
                    </div>
                </div>
            </div>
            <Button
                class="!absolute left-6 top-1/2 z-10 -translate-y-1/2"
                icon="chevron-left"
                variant="primary"
                round
                :title="__('fairu::browser.previous')"
                @click.stop.prevent="scrollPrev" />
            <Button
                class="!absolute right-6 top-1/2 z-10 -translate-y-1/2"
                icon="chevron-right"
                variant="primary"
                round
                :title="__('fairu::browser.next')"
                @click.stop.prevent="scrollNext" />
        </div>

        <!-- Thumbnail strip -->
        <div class="px-4 pb-4 pt-2 border-t border-gray-200 dark:border-gray-700">
            <div ref="thumbsRef" class="embla-thumbs overflow-hidden">
                <div class="embla-thumbs__container flex gap-2">
                    <button
                        v-for="(item, index) in items"
                        :key="'thumb-' + item.id"
                        type="button"
                        class="embla-thumbs__slide shrink-0 grow-0 size-16 rounded overflow-hidden bg-gray-100 dark:bg-gray-800 ring-2 transition-all"
                        :class="index === currentIndex ? 'ring-blue-500 opacity-100' : 'ring-transparent opacity-60 hover:opacity-100'"
                        @click="selectThumb(index)">
                        <img
                            v-if="meta.proxy && item?.blocked !== true && isMediaItem(item)"
                            draggable="false"
                            :src="thumbnailUrl(item, 128)"
                            class="size-full object-cover" />
                        <div
                            v-else
                            class="grid size-full place-items-center text-gray-400">
                            <i class="material-symbols-outlined" style="font-size: 28px">description</i>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
