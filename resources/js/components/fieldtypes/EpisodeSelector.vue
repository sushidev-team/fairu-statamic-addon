<script setup>
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Combobox, Description } from '@statamic/cms/ui';
import { config } from '@statamic/cms/api';

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

/**
 * Two controls, because an episode is only addressable through its show: pick the
 * channel, then the episode inside it. The episode may be left empty — that is
 * the show as a whole, which is what a page falls back to before its first
 * episode is out.
 */
const channels = ref([]);
const episodes = ref([]);
const loadingChannels = ref(true);
const loadingEpisodes = ref(false);
const error = ref(null);

const channel = ref(props.value?.channel ?? null);
const episode = ref(props.value?.episode ?? null);

const channelOptions = computed(() =>
    channels.value.map((entry) => ({
        value: entry.id,
        label: entry.kind ? `${entry.name} — ${entry.kind}` : entry.name,
    })),
);

const episodeOptions = computed(() =>
    episodes.value.map((entry) => ({
        value: entry.id,
        label: entry.number ? `${entry.number} · ${entry.name}` : entry.name,
    })),
);

async function post(endpoint, body = {}) {
    const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': config.get('csrfToken'),
        },
        body: JSON.stringify({ connection: props.meta.connection, ...body }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    return res.json();
}

async function loadEpisodes(id) {
    episodes.value = [];

    if (!id) return;

    loadingEpisodes.value = true;

    try {
        const body = await post(props.meta.episodes_endpoint, { channel: id });
        episodes.value = body?.data ?? [];

        // The endpoint answers 200 with an empty list and a message when Fairu
        // itself refused — an unreachable workspace is not a broken field.
        if (body?.message) error.value = body.message;
    } catch (err) {
        error.value = __('fairu::fieldtype.episode.load_error') ?? err?.message;
    } finally {
        loadingEpisodes.value = false;
    }
}

onMounted(async () => {
    try {
        const body = await post(props.meta.channels_endpoint);
        channels.value = body?.data ?? [];

        if (body?.message) error.value = body.message;
    } catch (err) {
        error.value = __('fairu::fieldtype.channel.load_error') ?? err?.message;
    } finally {
        loadingChannels.value = false;
    }

    await loadEpisodes(channel.value);
});

watch(channel, async (id) => {
    // An episode belongs to one show, so it cannot survive a change of show.
    episode.value = null;

    await loadEpisodes(id);

    push();
});

watch(episode, push);

function push() {
    update(channel.value ? { channel: channel.value, episode: episode.value ?? null } : null);
}
</script>

<template>
    <div class="flex w-full flex-col gap-2">
        <Combobox
            v-model="channel"
            :options="channelOptions"
            :disabled="loadingChannels"
            clearable
            :placeholder="__('fairu::fieldtype.channel.select')" />

        <Combobox
            v-model="episode"
            :options="episodeOptions"
            :disabled="!channel || loadingEpisodes"
            clearable
            :placeholder="__('fairu::fieldtype.episode.select')" />

        <Description v-if="error" class="text-red-500">{{ error }}</Description>
        <Description v-else-if="!loadingChannels && !channels.length">
            {{ __('fairu::fieldtype.channel.empty') }}
        </Description>
        <Description v-else-if="channel && !loadingEpisodes && !episodes.length">
            {{ __('fairu::fieldtype.episode.empty') }}
        </Description>
        <Description v-else-if="channel && !episode">
            {{ __('fairu::fieldtype.episode.optional') }}
        </Description>
    </div>
</template>
