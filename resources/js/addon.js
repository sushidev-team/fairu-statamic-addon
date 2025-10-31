import FairuFieldtype from './components/fieldtypes/Fairu.vue';
import FolderSelectorFieldtype from './components/fieldtypes/FolderSelector.vue';
import Dropzone from './components/Dropzone.vue';
import FairuBrowser from './components/FairuBrowser.vue';
import InputCheckbox from './components/input/InputCheckbox.vue';
import BrowserListItem from './components/browser/BrowserListItem.vue';
import Folder from './components/browser/Folder.vue';

Statamic.booting(() => {
    Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
    Statamic.$components.register('folder-selector_fieldtype', FolderSelectorFieldtype);
    Statamic.$components.register('fairu-browser', FairuBrowser);
    Statamic.$components.register('dropzone', Dropzone);
    Statamic.$components.register('folder', Folder);
    Statamic.$components.register('browser-list-item', BrowserListItem);
    Statamic.$components.register('input-checkbox', InputCheckbox);
});
