<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue';
import { Combobox, Description } from '@statamic/cms/ui';
import { config } from '@statamic/cms/api';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

/**
 * The shared body of the gallery and channel pickers.
 *
 * Both are the same control over a different list: fetch what the workspace has,
 * let the editor pick one, store its id. A workspace has tens of galleries and a
 * handful of shows, so the list is fetched once and filtered in the browser —
 * searching over the wire would be a round trip per keystroke for a list that
 * fits in a dropdown.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    endpoint: { type: String, required: true },
    connection: { type: String, default: 'default' },
    placeholder: { type: String, default: null },
    emptyText: { type: String, default: null },
    errorText: { type: String, default: null },
    /** Rendered under the label of every option, when the entry has one. */
    subtitle: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue']);

const entries = ref([]);
const loading = ref(true);
const error = ref(null);

const options = computed(() =>
    entries.value.map((entry) => ({
        value: entry.id,
        label: props.subtitle?.(entry) ? `${entry.name} — ${props.subtitle(entry)}` : entry.name,
    })),
);

const selected = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value ?? null),
});

onMounted(async () => {
    try {
        const res = await fetch(props.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': config.get('csrfToken'),
            },
            body: JSON.stringify({ connection: props.connection }),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const body = await res.json();
        entries.value = body?.data ?? [];

        // The endpoint answers 200 with an empty list and a message when Fairu
        // itself refused — an unreachable workspace is not a broken field.
        if (body?.message) error.value = body.message;
    } catch (err) {
        error.value = props.errorText ?? err?.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="flex w-full flex-col gap-1">
        <Combobox
            v-model="selected"
            :options="options"
            :disabled="loading"
            clearable
            :placeholder="placeholder ?? __('Select...')" />

        <Description v-if="error" class="text-red-500">{{ error }}</Description>
        <Description v-else-if="!loading && !entries.length">{{ emptyText }}</Description>
    </div>
</template>
