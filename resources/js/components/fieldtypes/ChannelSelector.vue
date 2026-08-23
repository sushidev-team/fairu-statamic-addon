<script setup>
import { getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import LibrarySelector from './LibrarySelector.vue';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

// Whether a show is watched or heard decides which player a template renders,
// so it belongs next to the name in the picker.
function subtitle(channel) {
    return channel.kind;
}
</script>

<template>
    <LibrarySelector
        :model-value="value"
        :endpoint="meta.endpoint"
        :connection="meta.connection"
        :placeholder="__('fairu::fieldtype.channel.select')"
        :empty-text="__('fairu::fieldtype.channel.empty')"
        :error-text="__('fairu::fieldtype.channel.load_error')"
        :subtitle="subtitle"
        @update:model-value="update" />
</template>
