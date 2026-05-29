import { computed } from 'vue';
import { config } from '@statamic/cms/api';

function hasFairuPermission(action) {
    const user = config.get('user');
    if (!user) return false;
    // Statamic exposes the current user's permissions as a plain string array.
    // Super users are represented by the literal 'super' permission (there is no
    // `user.super` boolean), so mirror Statamic's own check rather than inventing one.
    const permissions = user.permissions || [];
    return permissions.includes('super') || permissions.includes(`${action} fairu assets`);
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
