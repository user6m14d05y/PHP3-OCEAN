import { createRouter, createWebHistory } from "vue-router";
import Home from "../Pages/Home.vue";
import ClientLayout from "../layouts/ClientLayout.vue";
import AdminLayout from "../layouts/AdminLayout.vue";
import AdminHome from "../Pages/admin/AdminHome.vue";
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
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
