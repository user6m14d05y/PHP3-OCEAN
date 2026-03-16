import { createRouter, createWebHistory } from "vue-router";
import Home from "../Pages/Home.vue";
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
