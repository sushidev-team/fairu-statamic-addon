import { inertia } from '@statamic/cms/api';
import FairuFieldtype from './components/fieldtypes/Fairu.vue';
import FolderSelector from './components/fieldtypes/FolderSelector.vue';
import FairuBrowserPage from './components/FairuBrowserPage.vue';

Statamic.booting(() => {
    Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
    Statamic.$components.register('folder_selector-fieldtype', FolderSelector);
    inertia.register('fairu/Browser', FairuBrowserPage);
});
