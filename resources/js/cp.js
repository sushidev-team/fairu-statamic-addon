import FairuFieldtype from './components/Fairu.vue';
import FairuBrowser from './components/FairuBrowser.vue';
import BrowserListItem from './components/browser/BrowserListItem.vue';
import Dropzone from './components/Dropzone.vue';
import InputCheckbox from './components/input/InputCheckbox.vue';

Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
Statamic.$components.register('fairu-browser', FairuBrowser);
Statamic.$components.register('browser-list-item', BrowserListItem);
Statamic.$components.register('dropzone', Dropzone);
Statamic.$components.register('input-checkbox', InputCheckbox);
