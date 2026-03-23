import { createRouter, createWebHistory } from "vue-router";

// User
import Home from "../Pages/Client/Home/Home.vue";
import Product from "../Pages/Client/Home/Product.vue";
import ProductDetail from "../Pages/Client/Home/productDetail.vue";
import ClientLayout from "../layouts/ClientLayout.vue";

// Admin
import AdminLayout from "../layouts/AdminLayout.vue";
import AdminHome from "../Pages/admin/AdminHome.vue";
import AdminProduct from "../Pages/admin/AdminProduct.vue";
import AdminCreateProduct from "../Pages/admin/AdminCreateProduct.vue";
import AdminEditProduct from "../Pages/admin/AdminEditProduct.vue";
import AdminUsers from "../Pages/admin/AdminUsers.vue";
import AdminCategory from "../Pages/admin/AdminCategory.vue";
import AdminStaff from "../Pages/admin/AdminStaff.vue";
import AdminContact from "../Pages/admin/AdminContact.vue";

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
            { path: "", name: "home", component: Home, meta: { title: 'Trang chủ' } },
            { path: "product", name: "product", component: Product, meta: { title: 'Sản phẩm' } },
            { path: "product/:id", name: "product-detail", component: ProductDetail, meta: { title: 'Chi tiết sản phẩm' } },
            { path: "about", name: "brand-story", component: BrandStory, meta: { title: 'Câu chuyện thương hiệu' } },
            { path: "careers", name: "careers", component: Careers, meta: { title: 'Tuyển dụng' } },
            { path: "terms", name: "terms", component: Terms, meta: { title: 'Điều khoản sử dụng' } },
            { path: "privacy", name: "privacy", component: Privacy, meta: { title: 'Chính sách bảo mật' } },
            { path: "faq", name: "faq", component: FAQ, meta: { title: 'Câu hỏi thường gặp' } },
            { path: "return-policy", name: "return-policy", component: ReturnPolicy, meta: { title: 'Chính sách đổi trả' } },
            { path: "contact", name: "contact", component: Contact, meta: { title: 'Liên hệ' } },
            { path: "shopping-guide", name: "shopping-guide", component: ShoppingGuide, meta: { title: 'Hướng dẫn mua hàng' } },
        ],
    },
    // Auth routes (không có layout header/footer)
    {
        path: "/client/login",
        name: "login",
        component: Login,
        meta: { guest: true, title: 'Đăng nhập' },
    },
    {
        path: "/client/register",
        name: "register",
        component: Register,
        meta: { guest: true, title: 'Đăng ký' },
    },
    {
        path: "/client/forgot",
        name: "forgot",
        component: Forgot,
        meta: { guest: true, title: 'Quên mật khẩu' },
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
                meta: { title: 'Tổng quan' },
            },
            {
                path: "product",
                name: "admin-product",
                component: AdminProduct,
                meta: { title: 'Quản lý sản phẩm' },
            },
            {
                path: "product/create",
                name: "admin-product-create",
                component: AdminCreateProduct,
                meta: { title: 'Thêm sản phẩm' },
            },
            {
                path: "product/edit/:id",
                name: "admin-product-edit",
                component: AdminEditProduct,
                meta: { title: 'Sửa sản phẩm' },
            },
            {
                path: "users",
                name: "admin-users",
                component: AdminUsers,
                meta: { roles: ['admin'], title: 'Quản lý khách hàng' },
            },
            {
                path: "category",
                name: "admin-category",
                component: AdminCategory,
                meta: { title: 'Quản lý danh mục' },
            },
            {
                path: "staff",
                name: "admin-staff",
                component: AdminStaff,
                meta: { roles: ['admin'], title: 'Quản lý nhân sự' },
            },
            {
                path: "contact",
                name: "admin-contact",
                component: AdminContact,
                meta: { title: 'Quản lý liên hệ' },
            },

        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
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
            // Không có quyền → redirect về trang chủ
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

// ==================== Dynamic Page Title ====================
router.afterEach((to) => {
    const title = to.meta.title;
    const isAdmin = to.matched.some(record => record.path === '/admin');

    if (title) {
        document.title = isAdmin ? `${title} | Ocean Admin` : `${title} | Ocean`;
    } else {
        document.title = 'Ocean';
    }
});

export default router;