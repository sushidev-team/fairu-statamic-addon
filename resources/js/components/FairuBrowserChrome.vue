<script setup>
import { Stack } from '@statamic/cms/ui';

defineProps({
    embedded: { type: Boolean, default: false },
    showFooter: { type: Boolean, default: true },
});

defineEmits(['close']);
</script>

<template>
    <Stack
        v-if="!embedded"
        open
        inset
        :wrap-slot="false"
        :show-close-button="false"
        @closed="$emit('close')">
        <slot name="header" />
        <slot />
        <template #footer-start>
            <slot name="footer-start" />
        </template>
        <template #footer-end>
            <slot name="footer-end" />
        </template>
    </Stack>
    <div
        v-else
        class="flex h-full min-h-0 min-w-0 flex-col">
        <slot name="header" />
        <slot />
        <div
            v-if="showFooter"
            class="flex shrink-0 items-center gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            <slot name="footer-start" />
        </div>
    </div>
</template>
