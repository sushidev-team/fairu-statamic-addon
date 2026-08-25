import { inertia } from '@statamic/cms/api';
import FairuFieldtype from './components/fieldtypes/Fairu.vue';
import FolderSelector from './components/fieldtypes/FolderSelector.vue';
import GallerySelector from './components/fieldtypes/GallerySelector.vue';
import ChannelSelector from './components/fieldtypes/ChannelSelector.vue';
import EpisodeSelector from './components/fieldtypes/EpisodeSelector.vue';
import FairuBrowserPage from './components/FairuBrowserPage.vue';
import CacheUtilityPage from './components/CacheUtilityPage.vue';

Statamic.booting(() => {
    Statamic.$components.register('fairu-fieldtype', FairuFieldtype);
    Statamic.$components.register('folder_selector-fieldtype', FolderSelector);
    Statamic.$components.register('fairu_gallery-fieldtype', GallerySelector);
    Statamic.$components.register('fairu_channel-fieldtype', ChannelSelector);
    Statamic.$components.register('fairu_episode-fieldtype', EpisodeSelector);
    inertia.register('fairu/Browser', FairuBrowserPage);
    inertia.register('fairu/CacheUtility', CacheUtilityPage);
});
