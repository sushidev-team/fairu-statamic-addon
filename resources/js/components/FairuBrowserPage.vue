<script setup>
import { defineAsyncComponent } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { Header } from '@statamic/cms/ui';
import { useFairuPermissions } from '../utils/permissions.js';

const FairuBrowser = defineAsyncComponent(() => import('./FairuBrowser.vue'));

const props = defineProps({
    meta: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Assets' },
});

const { canUpload } = useFairuPermissions();
</script>

<template>
    <Head :title="__(title)" />

    <div
        class="flex flex-col"
        style="height: calc(100dvh - 6.5rem);">
        <Header :title="__(title)" icon="assets" />
        <div class="min-h-0 min-w-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <FairuBrowser
                embedded
                :meta="meta"
                :config="props.config"
                :can-upload="canUpload" />
        </div>
    </div>
</template>
