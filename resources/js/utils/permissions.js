import { computed } from 'vue';
import { config } from '@statamic/cms/api';

function hasFairuPermission(action) {
    const user = config.get('user');
    if (!user) return false;
    if (user.super) return true;
    return (user.permissions || []).includes(`${action} fairu assets`);
}

export function useFairuPermissions() {
    return {
        canView: computed(() => hasFairuPermission('view')),
        canUpload: computed(() => hasFairuPermission('upload')),
        canEdit: computed(() => hasFairuPermission('edit')),
        canRename: computed(() => hasFairuPermission('rename')),
        canMove: computed(() => hasFairuPermission('move')),
        canDelete: computed(() => hasFairuPermission('delete')),
    };
}
