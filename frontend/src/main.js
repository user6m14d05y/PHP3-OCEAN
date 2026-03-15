import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import './assets/main.css';


// CLIENT
import Home from './Pages/Client/Home/Index.vue';
import ClientHome from './Pages/Client/Home/Index.vue';
import ClientLogin from './Pages/Client/Auth/login.vue';
import ClientRegister from './Pages/Client/Auth/Register.vue';
import ClientForgot from './Pages/Client/Auth/Forgot.vue';

// ADMIN
import AdminDashboard from './Pages/Admin/dashboard.vue';

const routes = [
  { path: '/', component: Home, name: 'home' },
  { path: '/client/home', component: ClientHome, name: 'client-home' },
  { path: '/client/login', component: ClientLogin, name: 'client-login' },
  { path: '/client/register', component: ClientRegister, name: 'client-register' },
  { path: '/client/forgot', component: ClientForgot, name: 'client-forgot' },
  { path: '/admin/dashboard', component: AdminDashboard, name: 'admin-dashboard' }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

const app = createApp(App);
app.use(router);
app.mount('#app');
