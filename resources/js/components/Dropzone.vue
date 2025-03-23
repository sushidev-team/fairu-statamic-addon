<template>
    <div
        @dragenter="dragenter"
        @dragover="dragover"
        @dragleave="dragleave"
        @drop="drop">
        <div
            class="relative"
            :class="{ 'pointer-events-none': dragging }">
            <input
                type="file"
                multiple
                class="hidden"
                ref="nativeFileField" />
            <div
                class="absolute inset-0 z-10 rounded pointer-events-none dark:fa-bg-dark-900 fa-flex fa-place-items-center fa-justify-center fa-bg-gray-100 fa-transition-opacity fa-duration-150"
                :class="dragging ? 'fa-opacity-90' : 'fa-opacity-0'"
                ><i class="inline-block mr-2 material-symbols-outlined">drive_folder_upload</i>
                <span>Drop files here</span>
            </div>
            <div class="relative">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        enabled: {
            type: Boolean,
            default: () => true,
        },
        extraData: {
            type: Object,
            default: () => ({}),
        },
    },

    data() {
        return {
            dragging: false,
            uploads: [],
        };
    },

    mounted() {
        this.$refs.nativeFileField.addEventListener('change', this.addNativeFileFieldSelections);
    },

    beforeDestroy() {
        this.$refs.nativeFileField.removeEventListener('change', this.addNativeFileFieldSelections);
    },

    watch: {
        uploads(uploads) {
            this.$emit('updated', uploads);
            this.processUploadQueue();
        },
        dragging(dragging) {
            console.log({ dragging });
        },
    },

    computed: {
        activeUploads() {
            return this.uploads.filter((u) => u.instance.state === 'started');
        },
    },

    methods: {
        browse() {
            this.$refs.nativeFileField.click();
        },

        addNativeFileFieldSelections(e) {
            for (let i = 0; i < e.target.files.length; i++) {
                this.addFile(e.target.files[i]);
            }
        },

        dragenter(e) {
            e.stopPropagation();
            e.preventDefault();
            this.dragging = true;
        },

        dragover(e) {
            e.stopPropagation();
            e.preventDefault();
        },

        dragleave(e) {
            // When dragging over a child, the parent will trigger a dragleave.
            if (e.target !== e.currentTarget) return;

            this.dragging = false;
        },

        drop(e) {
            e.stopPropagation();
            e.preventDefault();
            this.dragging = false;

            const { files, items } = e.dataTransfer;

            this.$emit('dropped', files);
        },
        addFile(file, data = {}) {
            if (!this.enabled) return;

            const id = uniqid();
            const upload = this.makeUpload(id, file, data);

            this.uploads.push({
                id,
                basename: file.name,
                extension: file.name.split('.').pop(),
                percent: 0,
                errorMessage: null,
                errorStatus: null,
                instance: upload,
                retry: (opts) => this.retry(id, opts),
            });
        },
    },
};
</script>
