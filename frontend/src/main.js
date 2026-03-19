import { createApp } from 'vue';
import App from './App.vue';
import './assets/main.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './bootstrap';
import router from './router';

const app = createApp(App);
app.use(router);
app.mount('#app');
