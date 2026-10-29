import { createApp } from 'vue';
import ElementPlus from 'element-plus';
import en from 'element-plus/es/locale/lang/en';
import App from './App.vue';

createApp(App).use(ElementPlus, { locale: en, size: 'large' }).mount('#app');
if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
