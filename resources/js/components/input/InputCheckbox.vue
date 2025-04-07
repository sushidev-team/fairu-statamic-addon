<template>
    <div class="fa-flex fa-h-6 fa-shrink-0 fa-items-center">
        <div class="fa-group fa-grid fa-size-4 fa-grid-cols-1">
            <input
                :id="id"
                :name="id"
                type="checkbox"
                :checked="checked"
                @change="emitChange"
                :disabled="disabled"
                class="fa-col-start-1 fa-row-start-1 fa-appearance-none fa-rounded fa-border fa-border-gray-300 fa-bg-white checked:fa-border-blue-600 checked:fa-bg-blue-600 indeterminate:fa-border-blue-600 indeterminate:fa-bg-blue-600 focus-visible:fa-outline focus-visible:fa-outline-2 focus-visible:fa-outline-offset-2 focus-visible:fa-outline-blue-600 disabled:fa-border-gray-300 disabled:fa-bg-gray-100 disabled:checked:fa-bg-gray-100 dark:fa-border-zinc-600 dark:fa-bg-zinc-700 forced-colors:fa-appearance-auto" />
            <svg
                class="fa-pointer-events-none fa-col-start-1 fa-row-start-1 fa-size-3.5 fa-self-center fa-justify-self-center fa-stroke-white group-has-[:disabled]:fa-stroke-gray-950/25"
                viewBox="0 0 14 14"
                fill="none">
                <path
                    class="fa-opacity-0 group-has-[:checked]:fa-opacity-100"
                    d="M3 8L6 11L11 3.5"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round" />
                <path
                    class="fa-opacity-0 group-has-[:indeterminate]:fa-opacity-100"
                    d="M3 7H11"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
        </div>
        <div
            class="fa-min-w-0 fa-flex-1 fa-text-sm/6"
            v-if="label">
            <label
                :for="id"
                class="font-medium fa-select-none fa-text-gray-900"
                >{{ label }}</label
            >
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
        id: String,
        label: String,
        checked: Boolean,
        disabled: {
            type: Object,
            default: () => false,
        },
    },
    methods: {
        emitChange() {
            this.$emit('change', !this.checked);
        },
    },

    computed: {},

    mounted() {},
};
</script>
