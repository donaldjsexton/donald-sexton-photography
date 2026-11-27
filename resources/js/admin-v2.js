import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import './admin-push';
import './admin-widgets';
import './gallery-uploader';

Alpine.plugin(persist);
window.Alpine = Alpine;
Alpine.start();
