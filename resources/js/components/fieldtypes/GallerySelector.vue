<script setup>
import { getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import LibrarySelector from './LibrarySelector.vue';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

// A gallery is usually recognised by when and where it was shot rather than by
// its name — two "Sommerfest" galleries a year apart are otherwise identical.
function subtitle(gallery) {
    return [gallery.date, gallery.location].filter(Boolean).join(' · ');
}
</script>

<template>
    <LibrarySelector
        :model-value="value"
        :endpoint="meta.endpoint"
        :connection="meta.connection"
        :placeholder="__('fairu::fieldtype.gallery.select')"
        :empty-text="__('fairu::fieldtype.gallery.empty')"
        :error-text="__('fairu::fieldtype.gallery.load_error')"
        :subtitle="subtitle"
        @update:model-value="update" />
</template>
