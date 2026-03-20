import { createRouter, createWebHistory } from "vue-router";
import Home from "../Pages/Home.vue";
import ClientLayout from "../layouts/ClientLayout.vue";
import AdminLayout from "../layouts/AdminLayout.vue";
import AdminHome from "../Pages/admin/AdminHome.vue";
import AdminProduct from "../Pages/admin/AdminProduct.vue";
import AdminCreateProduct from "../Pages/admin/AdminCreateProduct.vue";
import AdminEditProduct from "../Pages/admin/AdminEditProduct.vue";
import AdminUsers from "../Pages/admin/AdminUsers.vue";
import AdminCategory from "../Pages/admin/AdminCategory.vue";
<<<<<<< HEAD
=======
import AdminStaff from "../Pages/admin/AdminStaff.vue";
import AdminContact from "../Pages/admin/AdminContact.vue";
>>>>>>> 2621a63c70a980b83874b7769cafbfec329fd7bd
import Login from "../Pages/Client/Auth/login.vue";
import Register from "../Pages/Client/Auth/Register.vue";
import Forgot from "../Pages/Client/Auth/Forgot.vue";
import GoogleCallback from "../Pages/Client/Auth/GoogleCallback.vue";

// Static pages
import BrandStory from "../Pages/Client/Static/BrandStory.vue";
import Careers from "../Pages/Client/Static/Careers.vue";
import Terms from "../Pages/Client/Static/Terms.vue";
import Privacy from "../Pages/Client/Static/Privacy.vue";
import FAQ from "../Pages/Client/Static/FAQ.vue";
import ReturnPolicy from "../Pages/Client/Static/ReturnPolicy.vue";
import Contact from "../Pages/Client/Static/Contact.vue";
import ShoppingGuide from "../Pages/Client/Static/ShoppingGuide.vue";

const routes = [
    {
        path: "/",
        component: ClientLayout,
        children: [
            { path: "", name: "home", component: Home },
            { path: "about", name: "brand-story", component: BrandStory },
            { path: "careers", name: "careers", component: Careers },
            { path: "terms", name: "terms", component: Terms },
            { path: "privacy", name: "privacy", component: Privacy },
            { path: "faq", name: "faq", component: FAQ },
            { path: "return-policy", name: "return-policy", component: ReturnPolicy },
            { path: "contact", name: "contact", component: Contact },
            { path: "shopping-guide", name: "shopping-guide", component: ShoppingGuide },
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
    {
        path: "/api/auth/google/callback",
        name: "google-callback",
        component: GoogleCallback,
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
                path: "product/edit/:id",
                name: "admin-product-edit",
                component: AdminEditProduct,
            },
            {
                path: "users",
                name: "admin-users",
                component: AdminUsers,
                meta: { roles: ['admin'] }, // Chỉ admin mới quản lý nhân sự
            },
            {
                path: "category",
                name: "admin-category",
                component: AdminCategory,
            },
            {
                path: "staff",
                name: "admin-staff",
                component: AdminStaff,
                meta: { roles: ['admin'] },
            },
            {
                path: "contact",
                name: "admin-contact",
                component: AdminContact,
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
