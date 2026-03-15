import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import './assets/main.css';

// Import Pages
import Home from './Pages/Home.vue';
import ClientHome from './Pages/Client/Home/Index.vue';
import ClientLogin from './Pages/Client/Auth/Login.vue';
import ClientRegister from './Pages/Client/Auth/Register.vue';
import ClientForgot from './Pages/Client/Auth/Forgot.vue';

const routes = [
  { path: '/', component: Home, name: 'home' },
  { path: '/client/home', component: ClientHome, name: 'client-home' },
  { path: '/client/login', component: ClientLogin, name: 'client-login' },
  { path: '/client/register', component: ClientRegister, name: 'client-register' },
  { path: '/client/forgot', component: ClientForgot, name: 'client-forgot' }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

const app = createApp(App);
app.use(router);
app.mount('#app');
