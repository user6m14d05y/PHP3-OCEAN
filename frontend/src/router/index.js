import { createRouter, createWebHistory } from "vue-router";

// Auth
import Home from "../Pages/Client/Home/Index.vue";
import ClientLogin from '../Pages/Client/Auth/login.vue';
import ClientRegister from '../Pages/Client/Auth/Register.vue';
import ClientForgot from '../Pages/Client/Auth/Forgot.vue';


import ClientLayout from "../layouts/ClientLayout.vue";
import AdminLayout from "../layouts/AdminLayout.vue";
import AdminHome from "../Pages/admin/AdminHome.vue";
import AdminProduct from "../Pages/admin/AdminProduct.vue";
import AdminCreateProduct from "../Pages/admin/AdminCreateProduct.vue";
const routes = [
    {
        path: "/",
        component: ClientLayout,
        children: [
            {
                path: "",
                name: "home",
                component: Home,
            },
            {
                path: "login",
                name: "login",
                component: ClientLogin,
            },
            {
                path: "register",
                name: "register",
                component: ClientRegister,
            },
            {
                path: "forgot",
                name: "forgot",
                component: ClientForgot,
            }
        ],
    },
    {
        path: "/admin",
        component: AdminLayout,
        children: [
            {
                path: "",
                name: "admin",
                component: AdminHome,
            },
            {
                path: "product",
                name: "admin-product",
                component: AdminProduct,
            },
            {
                path: "product/create",
                name: "admin-product-create",
                component: AdminCreateProduct,
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
