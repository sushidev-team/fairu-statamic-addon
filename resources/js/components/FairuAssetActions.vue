<script setup>
import { ref, getCurrentInstance, defineAsyncComponent } from 'vue';
import { toast } from '@statamic/cms/api';
import { Modal, ConfirmationModal, Button, Input, Description } from '@statamic/cms/ui';
import { fairuDeleteFile, fairuRenameFile, fairuMoveFile } from '../utils/fetches';

const FairuBrowser = defineAsyncComponent(() => import('./FairuBrowser.vue'));

const __ = getCurrentInstance().appContext.config.globalProperties.__;

const props = defineProps({
    meta: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['renamed', 'moved', 'deleted']);

const currentAsset = ref(null);
const renameOpen = ref(false);
const renameValue = ref('');
const renameSaving = ref(false);
const deleteOpen = ref(false);
const deleteSaving = ref(false);
const moveOpen = ref(false);
const moveSaving = ref(false);

function openRename(asset) {
    if (!asset?.id) return;
    currentAsset.value = asset;
    renameValue.value = asset.name ?? '';
    renameOpen.value = true;
}

function openDelete(asset) {
    if (!asset?.id) return;
    currentAsset.value = asset;
    deleteOpen.value = true;
}

function openMove(asset) {
    if (!asset?.id) return;
    currentAsset.value = asset;
    moveOpen.value = true;
}

async function confirmRename() {
    const asset = currentAsset.value;
    const name = renameValue.value?.trim();
    if (!asset?.id || !name) return;
    renameSaving.value = true;
    try {
        const res = await fairuRenameFile(asset.id, name);
        const updated = res?.data ?? res;
        toast.success(__('fairu::fieldtype.actions.rename_success'));
        emit('renamed', { id: asset.id, name: updated?.name ?? name, asset: updated });
        renameOpen.value = false;
    } catch (err) {
        console.error(err);
        toast.error(err?.message || __('fairu::fieldtype.actions.rename_error'));
    } finally {
        renameSaving.value = false;
    }
}

async function confirmDelete() {
    const asset = currentAsset.value;
    if (!asset?.id) return;
    deleteSaving.value = true;
    try {
        await fairuDeleteFile(asset.id);
        toast.success(__('fairu::fieldtype.actions.delete_success'));
        emit('deleted', { id: asset.id });
        deleteOpen.value = false;
    } catch (err) {
        console.error(err);
        toast.error(err?.message || __('fairu::fieldtype.actions.delete_error'));
    } finally {
        deleteSaving.value = false;
    }
}

async function handleFolderSelected(folder) {
    const asset = currentAsset.value;
    if (!asset?.id) return;
    const parent = folder?.id ?? null;
    moveSaving.value = true;
    try {
        await fairuMoveFile(asset.id, parent);
        toast.success(__('fairu::fieldtype.actions.move_success'));
        emit('moved', { id: asset.id, parent });
        moveOpen.value = false;
    } catch (err) {
        console.error(err);
        toast.error(err?.message || __('fairu::fieldtype.actions.move_error'));
    } finally {
        moveSaving.value = false;
    }
}

defineExpose({ openRename, openDelete, openMove });
</script>

<template>
    <!-- Rename modal -->
    <Modal
        v-if="renameOpen"
        v-model:open="renameOpen"
        :title="__('fairu::fieldtype.actions.rename_title')">
        <Input
            v-model="renameValue"
            :placeholder="__('fairu::fieldtype.actions.rename_placeholder')"
            :focus="true"
            @keyup.enter="confirmRename" />
        <template #footer>
            <div class="flex justify-end gap-2 pt-3 pb-1">
                <Button :text="__('fairu::browser.cancel')" :disabled="renameSaving" @click="renameOpen = false" />
                <Button
                    variant="primary"
                    :text="__('fairu::fieldtype.actions.rename_title')"
                    :loading="renameSaving"
                    :disabled="!renameValue?.trim()"
                    @click="confirmRename" />
            </div>
        </template>
    </Modal>

    <!-- Delete confirmation -->
    <ConfirmationModal
        v-if="deleteOpen"
        :open="deleteOpen"
        @update:open="deleteOpen = $event"
        :title="__('fairu::fieldtype.actions.delete_title')"
        :danger="true"
        :loading="deleteSaving"
        blur
        @confirm="confirmDelete">
        <Description>{{ __('fairu::fieldtype.actions.delete_description') }}</Description>
    </ConfirmationModal>

    <!-- Move: folder picker -->
    <FairuBrowser
        v-if="moveOpen"
        selectionType="folder"
        :meta="meta"
        :config="config"
        :canUpload="false"
        @close="moveOpen = false"
        @selected="handleFolderSelected" />
</template>
