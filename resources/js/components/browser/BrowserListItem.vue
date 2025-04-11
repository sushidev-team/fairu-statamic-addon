<template>
    <div
        class="grid items-center gap-2 px-2 py-1 fa-min-h-12 fa-select-none fa-grid-cols-[1fr,auto]"
        :class="disabled ? 'fa-opacity-50' : ''"
        @click="toggleSelection"
        v-if="asset.type !== 'folder'">
        <div class="flex items-center w-full gap-1 cursor-pointer grow">
            <input-checkbox
                v-if="multiselect"
                class="fa-mr-1.5"
                :id="asset?.id"
                :checked="selected" />
            <div class="flex items-center justify-center flex-none overflow-hidden bg-gray-300 rounded-full fa-size-8">
                <img
                    v-if="meta.proxy && asset?.blocked != true && asset?.mime?.startsWith('image/')"
                    :src="`${meta.proxy}/${asset.id}/thumbnail.webp?width=34&height=34`"
                    class="object-cover" />
                <span
                    class="block text-gray-600"
                    style="font-size: 8px"
                    v-if="asset?.mime?.startsWith('image/') == false">
                    {{ getExtension(asset.mime) }}
                </span>
            </div>
            <div class="flex items-center gap-2 text-sm grow"> {{ asset.name }} </div>
        </div>
        <div class="flex gap-1">
            <a
                @click.stop
                :href="meta.file + '/' + asset.id"
                target="_blank"
                :title="__('fairu::browser.edit_in_fairu')"
                class="flex gap-1 text-xs cursor-pointer"
                ><i
                    class="text-lg text-gray-300 material-symbols-outlined fa-pointer-events-none dark:!fa-text-gray-600 dark:hover:!fa-text-blue-500"
                    >open_in_new</i
                >
            </a>
        </div>
    </div>
</template>

<script>
export default {
    components: {},

    data() {
        return {};
    },
    props: {
        asset: null,
        meta: null,
        selected: Boolean,
        multiselect: Boolean,
        disabled: Boolean,
    },
    methods: {
        toggleSelection() {
            if (this.disabled) return;
            this.$emit('change', { asset: this.asset, selected: !this.selected });
        },
        getExtension(mime) {
            const parts = mime.split('/');
            if (parts.length == 2) {
                return parts[1];
            }
            return 'n/a';
        },
    },

    computed: {},

    mounted() {},
};
</script>
