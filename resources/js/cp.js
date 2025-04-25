import FairuFieldtype from './components/fieldtypes/Fairu.vue';
import FairuBrowser from './components/FairuBrowser.vue';
import BrowserListItem from './components/browser/BrowserListItem.vue';
import Folder from './components/browser/Folder.vue';
import Dropzone from './components/Dropzone.vue';
import InputCheckbox from './components/input/InputCheckbox.vue';
import FolderSelector from './components/fieldtypes/FolderSelector.vue';

Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
Statamic.$components.register('fairu-browser', FairuBrowser);
Statamic.$components.register('browser-list-item', BrowserListItem);
Statamic.$components.register('folder', Folder);
Statamic.$components.register('dropzone', Dropzone);
Statamic.$components.register('input-checkbox', InputCheckbox);

Statamic.booting(() => {
    Statamic.component('folder_selector-fieldtype', FolderSelector);
});
