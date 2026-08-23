<script setup>
import { ref } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import {
    Header,
    Panel,
    PanelHeader,
    Heading,
    Card,
    Description,
    Badge,
    Button,
    Checkbox,
    Textarea,
    Alert,
    CommandPaletteItem,
} from '@statamic/cms/ui';
import { config } from '@statamic/cms/api';

const props = defineProps({
    meta: { type: Object, required: true },
    staticCache: { type: Object, required: true },
    connection: { type: Object, required: true },
    clearMetaUrl: { type: String, required: true },
    testConnectionUrl: { type: String, required: true },
    purgeDeliveryUrl: { type: String, required: true },
    maxIds: { type: Number, default: 50 },
});

function post(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': config.get('csrfToken'),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
}

const clearing = ref(false);
const alsoFlushStatic = ref(false);

function clearMeta() {
    clearing.value = true;

    router.post(
        props.clearMetaUrl,
        { static: alsoFlushStatic.value },
        {
            preserveScroll: true,
            onFinish: () => (clearing.value = false),
        },
    );
}

const testing = ref(false);
const testResult = ref(null);

async function testConnection() {
    testing.value = true;
    testResult.value = null;

    try {
        const res = await post(props.testConnectionUrl);
        testResult.value = await res.json();
    } catch (err) {
        testResult.value = { ok: false, message: err?.message };
    } finally {
        testing.value = false;
    }
}

const purgeIds = ref('');
const purging = ref(false);
const purgeResult = ref(null);

async function purgeDelivery() {
    purging.value = true;
    purgeResult.value = null;

    try {
        const res = await post(props.purgeDeliveryUrl, { ids: purgeIds.value });
        const body = await res.json();

        // A 422 is the validation error for an empty textarea, and it arrives
        // in Laravel's own shape rather than in this endpoint's.
        purgeResult.value = res.ok ? body : { ok: false, message: body?.message };

        if (purgeResult.value?.ok) purgeIds.value = '';
    } catch (err) {
        purgeResult.value = { ok: false, message: err?.message };
    } finally {
        purging.value = false;
    }
}
</script>

<template>
    <Head :title="[__('fairu::utility.title'), __('Utilities')]" />

    <div class="max-w-page mx-auto">
        <Header :title="__('fairu::utility.title')" icon="cache" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Panel class="flex h-full flex-col">
                <PanelHeader class="flex min-h-10 items-center justify-between">
                    <Heading>{{ __('fairu::utility.meta_cache') }}</Heading>
                    <CommandPaletteItem
                        category="Actions"
                        :text="[__('fairu::utility.clear'), __('fairu::utility.meta_cache')]"
                        icon="live-preview"
                        :action="clearMeta"
                        v-slot="{ text }">
                        <Button
                            :text="clearing ? __('fairu::utility.clearing') : __('fairu::utility.clear')"
                            :disabled="clearing"
                            size="sm"
                            @click="clearMeta" />
                    </CommandPaletteItem>
                </PanelHeader>
                <Card class="flex-1">
                    <Description>{{ __('fairu::utility.meta_cache_description') }}</Description>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Badge :prepend="__('fairu::utility.driver')">{{ meta.driver }}</Badge>
                        <Badge :prepend="__('fairu::utility.fresh_for')">
                            {{ meta.stale }} {{ __('fairu::utility.minutes') }}
                        </Badge>
                        <Badge :prepend="__('fairu::utility.stale_until')">
                            {{ meta.expires }} {{ __('fairu::utility.minutes') }}
                        </Badge>
                        <Badge :prepend="__('fairu::utility.namespace')">v{{ meta.version }}</Badge>
                        <Badge :prepend="__('fairu::utility.last_cleared')">
                            {{ meta.clearedAt ?? __('fairu::utility.never_cleared') }}
                        </Badge>
                        <Badge :prepend="__('fairu::utility.coalescing')">
                            {{ meta.coalescing ? __('fairu::utility.enabled') : __('fairu::utility.disabled') }}
                        </Badge>
                    </div>

                    <Alert
                        v-if="meta.debug"
                        class="mt-4"
                        variant="warning"
                        :text="__('fairu::utility.debug_notice')" />

                    <div v-if="staticCache.enabled" class="mt-4">
                        <Checkbox
                            v-model="alsoFlushStatic"
                            :label="__('fairu::utility.also_flush_static')"
                            :description="__('fairu::utility.also_flush_static_description')" />
                    </div>
                </Card>
            </Panel>

            <Panel class="flex h-full flex-col">
                <PanelHeader class="flex min-h-10 items-center justify-between">
                    <Heading>{{ __('fairu::utility.connection') }}</Heading>
                    <Button
                        :text="testing ? __('fairu::utility.testing') : __('fairu::utility.test')"
                        :disabled="testing || !connection.configured"
                        size="sm"
                        @click="testConnection" />
                </PanelHeader>
                <Card class="flex-1">
                    <Description>{{ __('fairu::utility.connection_description') }}</Description>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Badge v-if="connection.tenant" :prepend="__('fairu::utility.tenant')">
                            {{ connection.tenant }}
                        </Badge>
                        <Badge :prepend="__('fairu::utility.api_url')">{{ connection.url }}</Badge>
                        <Badge :prepend="__('fairu::utility.proxy_url')">{{ connection.proxy }}</Badge>
                    </div>

                    <Alert
                        v-if="!connection.configured"
                        class="mt-4"
                        variant="error"
                        :text="__('fairu::utility.not_configured')" />

                    <Alert
                        v-else-if="testResult?.ok"
                        class="mt-4"
                        variant="success"
                        :heading="__('fairu::utility.connection_ok')"
                        :text="testResult.tenant" />

                    <Alert
                        v-else-if="testResult"
                        class="mt-4"
                        variant="error"
                        :heading="__('fairu::utility.connection_failed')"
                        :text="testResult.message" />
                </Card>
            </Panel>

            <Panel class="flex h-full flex-col lg:col-span-2">
                <PanelHeader class="flex min-h-10 items-center justify-between">
                    <Heading>{{ __('fairu::utility.delivery_cache') }}</Heading>
                    <div class="flex items-center gap-2">
                        <Badge>{{ __('fairu::utility.max_ids', { max: maxIds }) }}</Badge>
                        <Button
                            :text="purging ? __('fairu::utility.purging') : __('fairu::utility.purge')"
                            :disabled="purging || !purgeIds.trim() || !connection.configured"
                            size="sm"
                            @click="purgeDelivery" />
                    </div>
                </PanelHeader>
                <Card class="flex-1">
                    <Description>{{ __('fairu::utility.delivery_cache_description') }}</Description>

                    <Textarea
                        v-model="purgeIds"
                        class="mt-3 font-mono"
                        :rows="4"
                        :placeholder="__('fairu::utility.delivery_ids_placeholder')" />

                    <Alert
                        v-if="purgeResult?.ok"
                        class="mt-4"
                        variant="success"
                        :heading="__('fairu::utility.purge_queued', { count: purgeResult.queued.length })"
                        :text="purgeResult.missing.length
                            ? __('fairu::utility.purge_missing', { ids: purgeResult.missing.join(', ') })
                            : null" />

                    <Alert
                        v-else-if="purgeResult"
                        class="mt-4"
                        variant="error"
                        :heading="__('fairu::utility.purge_failed')"
                        :text="purgeResult.message" />

                    <Description class="mt-3">{{ __('fairu::utility.purge_needs_permission') }}</Description>
                </Card>
            </Panel>
        </div>
    </div>
</template>
