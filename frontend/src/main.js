import { createApp } from 'vue';
import App from './App.vue';
import './assets/tailwind.css';
import './assets/main.css';
import './bootstrap';
import router from './router';

const app = createApp(App);
app.use(router);
app.mount('#app');
