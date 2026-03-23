import { createRouter, createWebHistory } from "vue-router";

// ==================== CORE LAYOUTS (eager load) ====================
import ClientLayout from "../layouts/ClientLayout.vue";

// ==================== HOME PAGES (eager load - trang chính) ====================
import Home from "../Pages/Client/Home/Home.vue";

// ==================== LAZY LOADED PAGES ====================
// Mỗi page sẽ được tải riêng khi user truy cập → giảm bundle size ban đầu

// Client pages
const Product = () => import("../Pages/Client/Home/Product.vue");
const ProductDetail = () => import("../Pages/Client/Home/productDetail.vue");

// Profile
const ProfileLayout = () => import("../Pages/Client/Profile/ProfileLayout.vue");
const ProfileInfo = () => import("../Pages/Client/Profile/ProfileInfo.vue");
const ProfileAddress = () => import("../Pages/Client/Profile/ProfileAddress.vue");

// Auth
const Login = () => import("../Pages/Client/Auth/login.vue");
const Register = () => import("../Pages/Client/Auth/Register.vue");
const Forgot = () => import("../Pages/Client/Auth/Forgot.vue");
const GoogleCallback = () => import("../Pages/Client/Auth/GoogleCallback.vue");

// Static pages
const BrandStory = () => import("../Pages/Client/Static/BrandStory.vue");
const Careers = () => import("../Pages/Client/Static/Careers.vue");
const Terms = () => import("../Pages/Client/Static/Terms.vue");
const Privacy = () => import("../Pages/Client/Static/Privacy.vue");
const FAQ = () => import("../Pages/Client/Static/FAQ.vue");
const ReturnPolicy = () => import("../Pages/Client/Static/ReturnPolicy.vue");
const Contact = () => import("../Pages/Client/Static/Contact.vue");
const ShoppingGuide = () => import("../Pages/Client/Static/ShoppingGuide.vue");

// Admin (lazy load toàn bộ - chỉ tải khi admin truy cập)
const AdminLayout = () => import("../layouts/AdminLayout.vue");
const AdminHome = () => import("../Pages/Admin/AdminHome.vue");
const AdminProduct = () => import("../Pages/Admin/AdminProduct.vue");
const AdminCreateProduct = () => import("../Pages/Admin/AdminCreateProduct.vue");
const AdminUsers = () => import("../Pages/Admin/AdminUsers.vue");
const AdminCategory = () => import("../Pages/Admin/AdminCategory.vue");
const AdminStaff = () => import("../Pages/Admin/AdminStaff.vue");
const AdminContact = () => import("../Pages/Admin/AdminContact.vue");

const routes = [
    {
        path: "/",
        component: ClientLayout,
        children: [
            { path: "", name: "home", component: Home },
            { path: "product", name: "product", component: Product },
            { path: "product/:slug", name: "product-detail", component: ProductDetail },
            { path: "about", name: "brand-story", component: BrandStory },
            { path: "careers", name: "careers", component: Careers },
            { path: "terms", name: "terms", component: Terms },
            { path: "privacy", name: "privacy", component: Privacy },
            { path: "faq", name: "faq", component: FAQ },
            { path: "return-policy", name: "return-policy", component: ReturnPolicy },
            { path: "contact", name: "contact", component: Contact },
            { path: "shopping-guide", name: "shopping-guide", component: ShoppingGuide },
            // Profile routes (nested layout)
            {
                path: "profile",
                component: ProfileLayout,
                meta: { requiresAuth: true },
                children: [
                    { path: "", name: "profile", component: ProfileInfo },
                    { path: "addresses", name: "profile-addresses", component: ProfileAddress },
                    { path: "orders", name: "profile-orders", component: ProfileInfo }, // placeholder
                    { path: "wishlist", name: "profile-wishlist", component: ProfileInfo }, // placeholder
                    { path: "change-password", name: "profile-change-password", component: ProfileInfo }, // placeholder
                ],
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
                component: () => import("../Pages/Admin/AdminEditProduct.vue"),
            },
            {
                path: "users",
                name: "admin-users",
                component: AdminUsers,
                meta: { roles: ['admin'] },
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
    // Scroll to top khi navigate
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        return { top: 0 };
    },
});

// ==================== Navigation Guard ====================
router.beforeEach((to, from) => {
    const token = localStorage.getItem('auth_token');
    const userData = localStorage.getItem('user');
    const user = userData ? JSON.parse(userData) : null;

    // Route yêu cầu đăng nhập
    if (to.matched.some(record => record.meta.requiresAuth)) {
        if (!token || !user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        // Kiểm tra role nếu route yêu cầu
        const requiredRoles = to.meta.roles || to.matched.find(r => r.meta.roles)?.meta.roles;
        if (requiredRoles && !requiredRoles.includes(user.role)) {
            return { name: 'home' };
        }
    }

    // Route dành cho guest (login, register) — nếu đã login thì redirect
    if (to.matched.some(record => record.meta.guest)) {
        if (token && user) {
            if (user.role === 'admin' || user.role === 'staff') {
                return { name: 'admin' };
            }
            return { name: 'home' };
        }
    }
});

export default router;
