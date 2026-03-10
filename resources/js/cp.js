import FairuFieldtype from './components/fieldtypes/Fairu.vue';
import FolderSelector from './components/fieldtypes/FolderSelector.vue';

Statamic.booting(() => {
    Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
    Statamic.$components.register('folder_selector-fieldtype', FolderSelector);
});
