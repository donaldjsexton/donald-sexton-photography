import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import './admin-push';

Alpine.plugin(persist);
window.Alpine = Alpine;
Alpine.start();
