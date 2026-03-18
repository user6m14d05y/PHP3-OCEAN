import { createRouter, createWebHistory } from "vue-router";
import Home from "../Pages/Home.vue";
import ClientLayout from "../layouts/ClientLayout.vue";
import AdminLayout from "../layouts/AdminLayout.vue";
import AdminHome from "../Pages/admin/AdminHome.vue";
import AdminProduct from "../Pages/admin/AdminProduct.vue";
import AdminCreateProduct from "../Pages/admin/AdminCreateProduct.vue";
import AdminUsers from "../Pages/admin/AdminUsers.vue";
import Login from "../Pages/Client/Auth/login.vue";
import Register from "../Pages/Client/Auth/Register.vue";
import Forgot from "../Pages/Client/Auth/Forgot.vue";

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
    // Auth routes (không có layout header/footer)
    {
        path: "/client/login",
        name: "login",
        component: Login,
        meta: { guest: true },
    },
    {
        path: "/client/register",
        name: "register",
        component: Register,
        meta: { guest: true },
    },
    {
        path: "/client/forgot",
        name: "forgot",
        component: Forgot,
        meta: { guest: true },
    },
    // Admin routes
    {
        path: "/admin",
        component: AdminLayout,
        meta: { requiresAuth: true, roles: ['admin', 'staff'] },
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
            {
                path: "users",
                name: "admin-users",
                component: AdminUsers,
                meta: { roles: ['admin'] }, // Chỉ admin mới quản lý nhân sự
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// ==================== Navigation Guard ====================
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');
    const userData = localStorage.getItem('user');
    const user = userData ? JSON.parse(userData) : null;

    // Route yêu cầu đăng nhập
    if (to.matched.some(record => record.meta.requiresAuth)) {
        if (!token || !user) {
            return next({ name: 'login', query: { redirect: to.fullPath } });
        }

        // Kiểm tra role nếu route yêu cầu
        const requiredRoles = to.meta.roles || to.matched.find(r => r.meta.roles)?.meta.roles;
        if (requiredRoles && !requiredRoles.includes(user.role)) {
            // Không có quyền → redirect về trang chủ
            return next({ name: 'home' });
        }
    }

    // Route dành cho guest (login, register) — nếu đã login thì redirect
    if (to.matched.some(record => record.meta.guest)) {
        if (token && user) {
            if (user.role === 'admin' || user.role === 'staff') {
                return next({ name: 'admin' });
            }
            return next({ name: 'home' });
        }
    }

    next();
});

export default router;
