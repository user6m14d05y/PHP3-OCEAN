<script setup>
import { ref, reactive, onMounted } from "vue";
import api from "@/axios";
import AdminCategoryFormTree from "@/components/AdminCategoryFormTree.vue";

const categories = ref([]);
const brands = ref([]);
const errors = ref({});
const product = reactive({
    category_id: "",
    brand_id: "",
    seller_id: "",
    name: "",
    slug: "",
    short_description: "",
    description: "",
    thumbnail_url: "",
    imagePreview: "",
    product_type: "simple", // simple | variable
    status: "draft",
    is_featured: false,
    min_price: "",
    max_price: "",
    stock: "",
    variants: [
        {
            color: "",
            images: [],
            imagePreview: "",
            sizes: [{ size: "", stock: 0, price: 0 }],
        },
    ],
});

const handleFetchCategories = async () => {
    try {
        const response = await api.get("/categories");
        categories.value = response.data.data;
    } catch (error) {
        console.error("Error fetching categories:", error);
    }
};

const handleFetchBrands = async () => {
    try {
        const response = await api.get("/brands");
        brands.value = response.data;
    } catch (error) {
        console.error("Error fetching brands:", error);
    }
};

const addVariant = () => {
    product.variants.push({
        color: "",
        images: [],
        imagePreview: "",
        sizes: [{ size: "", stock: 0, price: 0 }],
    });
};

// xoá biến thể
const removeVariant = (index) => {
    product.variants.splice(index, 1);
};

// thêm kích cỡ
const addSize = (variantIndex) => {
    product.variants[variantIndex].sizes.push({ size: "", stock: 0, price: 0 });
};

// xoá kích cỡ
const removeSize = (variantIndex, sizeIndex) => {
    product.variants[variantIndex].sizes.splice(sizeIndex, 1);
};

// logic đơn giản cho ảnh
// xử lý ảnh đại diện
const handleThumbnailChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        product.thumbnail_url = file;
        product.imagePreview = URL.createObjectURL(file);
    }
};

// xử lý ảnh biến thể
const handleVariantImageChange = (event, index) => {
    const file = event.target.files[0];
    if (file) {
        product.variants[index].images = [file];
        product.variants[index].imagePreview = URL.createObjectURL(file);
    }
};

// xử lý slug tự động
const generateSlug = () => {
    if (product.name) {
        product.slug = product.name
            .toLowerCase()
            .replace(/\s+/g, "-")
            .replace(/[^a-z0-9-]/g, "");
    }
};

// xử lý giá tối thiểu và tối đa
const updatePriceRange = () => {
    if (product.product_type === "simple") {
        product.min_price = product.max_price = 0;
    } else {
        const prices = product.variants.flatMap((v) =>
            v.sizes.map((s) => s.price),
        );
        product.min_price = Math.min(...prices);
        product.max_price = Math.max(...prices);
    }
};

// validate form
const validateForm = () => {
    errors.value = {};
    let isValid = true;
    if (!product.name) {
        errors.value.name = "Tên sản phẩm là bắt buộc";
        isValid = false;
    }
    if (!product.slug) {
        errors.slug = "Slug là bắt buộc";
        isValid = false;
    }
    if (!product.category_id) {
        errors.category_id = "Danh mục là bắt buộc";
        isValid = false;
    }
    if (!product.brand_id) {
        errors.brand_id = "Thương hiệu là bắt buộc";
        isValid = false;
    }
    if (!product.seller_id) {
        errors.seller_id = "Người bán là bắt buộc";
        isValid = false;
    }
    if (!product.thumbnail_url) {
        errors.thumbnail_url = "Ảnh đại diện là bắt buộc";
        isValid = false;
    }
    if (!product.product_type) {
        errors.product_type = "Loại sản phẩm là bắt buộc";
        isValid = false;
    }
    if (!product.status) {
        errors.status = "Trạng thái là bắt buộc";
        isValid = false;
    }
    if (!product.is_featured) {
        errors.is_featured = "Nổi bật là bắt buộc";
        isValid = false;
    }
    if (!product.min_price && product.product_type === "simple") {
        errors.value.min_price = "Giá tối thiểu là bắt buộc";
        isValid = false;
    }
    if (!product.max_price && product.product_type === "simple") {
        errors.value.max_price = "Giá tối đa là bắt buộc";
        isValid = false;
    }
    if (product.product_type === "simple" && product.stock === "") {
        errors.value.stock = "Số lượng là bắt buộc";
        isValid = false;
    }
    if (product.product_type === "variable") {
        if (!product.variants) {
            errors.value.variants = "Biến thể là bắt buộc";
            isValid = false;
        }
        if (!product.variants.length) {
            errors.value.variants = "Biến thể là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].color) {
            // Note: need to initialize errors.variants if not exist
            if (!errors.value.variants) errors.value.variants = [{}];
            errors.value.variants[0].color = "Màu sắc là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].images) {
            if (!errors.value.variants) errors.value.variants = [{}];
            errors.value.variants[0].images = "Ảnh là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].sizes) {
            if (!errors.value.variants) errors.value.variants = [{}];
            errors.value.variants[0].sizes = "Kích cỡ là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].sizes.length) {
            if (!errors.value.variants) errors.value.variants = [{}];
            errors.value.variants[0].sizes = "Kích cỡ là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].sizes[0].size) {
            if (!errors.value.variants) errors.value.variants = [{}];
            if (!errors.value.variants[0].sizes)
                errors.value.variants[0].sizes = [{}];
            errors.value.variants[0].sizes[0].size = "Kích cỡ là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].sizes[0].stock) {
            if (!errors.value.variants) errors.value.variants = [{}];
            if (!errors.value.variants[0].sizes)
                errors.value.variants[0].sizes = [{}];
            errors.value.variants[0].sizes[0].stock = "Số lượng là bắt buộc";
            isValid = false;
        }
        if (!product.variants[0].sizes[0].price) {
            if (!errors.value.variants) errors.value.variants = [{}];
            if (!errors.value.variants[0].sizes)
                errors.value.variants[0].sizes = [{}];
            errors.value.variants[0].sizes[0].price = "Giá là bắt buộc";
            isValid = false;
        }
    }
    return isValid;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        console.error("Form validation failed", errors.value);
        return;
    }

    const formData = new FormData();
    formData.append("name", product.name);
    formData.append("slug", product.slug);
    formData.append("category_id", product.category_id);
    formData.append("brand_id", product.brand_id);
    formData.append("seller_id", product.seller_id);
    formData.append("short_description", product.short_description || "");
    formData.append("description", product.description || "");
    formData.append("product_type", product.product_type);
    formData.append("status", product.status);
    formData.append("is_featured", product.is_featured ? 1 : 0);

    if (product.thumbnail_url instanceof File) {
        formData.append("thumbnail", product.thumbnail_url);
    }

    if (product.product_type === "simple") {
        formData.append("min_price", product.min_price || 0);
        formData.append("max_price", product.max_price || 0);
        formData.append("stock", product.stock || 0);
    } else {
        product.variants.forEach((variant, vIndex) => {
            formData.append(`variants[${vIndex}][color]`, variant.color);
            if (
                variant.images &&
                variant.images.length > 0 &&
                variant.images[0] instanceof File
            ) {
                formData.append(
                    `variants[${vIndex}][image]`,
                    variant.images[0],
                );
            }
            variant.sizes.forEach((size, sIndex) => {
                formData.append(
                    `variants[${vIndex}][sizes][${sIndex}][size]`,
                    size.size,
                );
                formData.append(
                    `variants[${vIndex}][sizes][${sIndex}][stock]`,
                    size.stock,
                );
                formData.append(
                    `variants[${vIndex}][sizes][${sIndex}][price]`,
                    size.price,
                );
            });
        });
    }

    try {
        const response = await api.post("/products", formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
        console.log("Product created successfully:", response.data);
        alert("Thêm sản phẩm thành công!");
        // router.push('/admin/product');
    } catch (error) {
        console.error("Error submitting form:", error);
        alert("Có lỗi xảy ra khi thêm sản phẩm.");
    }
};

onMounted(() => {
    handleFetchCategories();
    handleFetchBrands();
});
</script>

<template>
    <div class="create-product-page">
        <form
            @submit.prevent="handleSubmit"
            enctype="multipart/form-data"
        >
            <!-- Page Header -->
            <div class="page-header animate-in">
            <div class="header-info">
                <div class="back-link">
                    <router-link to="/admin/product">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Trở Về
                    </router-link>
                </div>
                <h1 class="page-title">Thêm Sản Phẩm Mới</h1>
                <p class="page-subtitle">
                    Thêm một mặt hàng mới vào danh mục cửa hàng của bạn
                </p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-outline">Hủy bỏ</button>
                <button type="submit" class="btn-primary">
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
                        ></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Lưu Sản Phẩm
                </button>
            </div>
        </div>

        <div class="form-container">
            <!-- Left Column: Main Information -->
            <div class="form-column main-col">
                <div
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.1s"
                >
                    <h3 class="card-title">Thông Tin Cơ Bản</h3>

                    <div class="form-group">
                        <label
                            >Tên Sản Phẩm <span class="required">*</span></label
                        >
                        <input
                            type="text"
                            v-model="product.name"
                            @input="generateSlug"
                            class="form-control"
                            placeholder="Ví dụ: Đồng Hồ Xanh Đại Dương"
                        />
                        <div v-if="errors.name" class="error-message text-red-500 text-sm mt-2">
                            {{ errors.name }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label
                            >Đường Dẫn (Slug)
                            <span class="required">*</span></label
                        >
                        <input
                            type="text"
                            v-model="product.slug"
                            class="form-control"
                            placeholder="dong-ho-xanh-dai-duong"
                        />
                        <div v-if="errors.slug" class="error-message">
                            {{ errors.slug }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mô Tả Ngắn</label>
                        <textarea
                            v-model="product.short_description"
                            class="form-control"
                            rows="2"
                            placeholder="Tổng quan ngắn gọn về sản phẩm"
                        ></textarea>
                        <div
                            v-if="errors.short_description"
                            class="error-message"
                        >
                            {{ errors.short_description }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mô Tả Chi Tiết</label>
                        <textarea
                            v-model="product.description"
                            class="form-control"
                            rows="5"
                            placeholder="Viết mô tả chi tiết cho sản phẩm..."
                        ></textarea>
                        <div v-if="errors.description" class="error-message">
                            {{ errors.description }}
                        </div>
                    </div>
                </div>

                <div
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.2s"
                >
                    <h3 class="card-title">Giá và Loại Sản Phẩm</h3>

                    <div class="form-group type-selector">
                        <label>Loại Sản Phẩm</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input
                                    type="radio"
                                    v-model="product.product_type"
                                    value="simple"
                                />
                                <div class="radio-box">
                                    <span class="radio-text">Sản Phẩm Đơn</span>
                                    <span class="radio-desc"
                                        >Một mặt hàng không có biến thể</span
                                    >
                                </div>
                            </label>
                            <label class="radio-label">
                                <input
                                    type="radio"
                                    v-model="product.product_type"
                                    value="variable"
                                />
                                <div class="radio-box">
                                    <span class="radio-text"
                                        >Sản Phẩm Biến Thể</span
                                    >
                                    <span class="radio-desc"
                                        >Sản phẩm có nhiều màu sắc/kích cỡ</span
                                    >
                                </div>
                            </label>
                        </div>
                        <div v-if="errors.product_type" class="error-message">
                            {{ errors.product_type }}
                        </div>
                    </div>

                    <div
                        class="price-grid"
                        v-if="product.product_type === 'simple'"
                    >
                        <div class="form-group">
                            <label>Giá Gốc</label>
                            <div class="input-with-prefix">
                                <span class="prefix">₫</span>
                                <input
                                    type="number"
                                    v-model="product.max_price"
                                    class="form-control"
                                    placeholder="0"
                                />
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Giá Khuyến Mãi (Không Bắt Buộc)</label>
                            <div class="input-with-prefix">
                                <span class="prefix">₫</span>
                                <input
                                    type="number"
                                    v-model="product.min_price"
                                    class="form-control"
                                    placeholder="0"
                                />
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 2">
                            <label
                                >Số Lượng Kho
                                <span class="required">*</span></label
                            >
                            <input
                                type="number"
                                v-model="product.stock"
                                class="form-control"
                                placeholder="0"
                            />
                            <div v-if="errors.stock" class="error-message">
                                {{ errors.stock }}
                            </div>
                        </div>
                        <div v-if="errors.min_price" class="error-message">
                            {{ errors.min_price }}
                        </div>
                        <div v-if="errors.max_price" class="error-message">
                            {{ errors.max_price }}
                        </div>
                    </div>
                </div>

                <!-- Variable Product Section -->
                <div
                    v-if="product.product_type === 'variable'"
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.3s"
                >
                    <div class="card-header-flex">
                        <h3 class="card-title">Biến Thể Sản Phẩm</h3>
                        <button class="btn-outline-small" @click="addVariant">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Thêm Biến Thể
                        </button>
                    </div>

                    <div
                        class="variant-item"
                        v-for="(variant, vIndex) in product.variants"
                        :key="vIndex"
                    >
                        <div class="variant-header">
                            <h4>
                                Thuộc Tính Màu/Tên Lựa Chọn #{{ vIndex + 1 }}
                            </h4>
                            <button
                                class="btn-icon-danger"
                                title="Xóa biến thể"
                                @click="removeVariant(vIndex)"
                            >
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path
                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                                    ></path>
                                </svg>
                            </button>
                        </div>

                        <div class="variant-body">
                            <div class="form-group">
                                <label>Tên Màu Sắc / Kiểu Dáng</label>
                                <input
                                    type="text"
                                    v-model="variant.color"
                                    class="form-control"
                                    placeholder="Ví dụ: Xanh Đại Dương"
                                />
                            </div>

                            <div class="form-group">
                                <label>Hình Ảnh Biến Thể</label>
                                <div class="image-upload-box small">
                                    <div
                                        v-if="variant.imagePreview"
                                        class="preview-container"
                                    >
                                        <img
                                            :src="variant.imagePreview"
                                            alt="Variant Preview"
                                            class="img-preview"
                                        />
                                        <button
                                            class="remove-img-btn"
                                            @click.prevent="
                                                variant.imagePreview = ''
                                            "
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <div v-else class="upload-placeholder">
                                        <svg
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            opacity="0.5"
                                        >
                                            <rect
                                                x="3"
                                                y="3"
                                                width="18"
                                                height="18"
                                                rx="2"
                                                ry="2"
                                            ></rect>
                                            <circle
                                                cx="8.5"
                                                cy="8.5"
                                                r="1.5"
                                            ></circle>
                                            <polyline
                                                points="21 15 16 10 5 21"
                                            ></polyline>
                                        </svg>
                                        <span>Bấm để tải ảnh lên</span>
                                        <input
                                            type="file"
                                            class="file-input-hide"
                                            accept="image/*"
                                            @change="
                                                (e) =>
                                                    handleVariantImageChange(
                                                        e,
                                                        vIndex,
                                                    )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="sizes-section">
                                <label>Kích Cỡ / Số Lượng</label>
                                <table class="sizes-table">
                                    <thead>
                                        <tr>
                                            <th>Phân Loại Size</th>
                                            <th>Số Lượng Kho</th>
                                            <th>Giá (₫)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(s, sIndex) in variant.sizes"
                                            :key="sIndex"
                                        >
                                            <td>
                                                <input
                                                    type="text"
                                                    v-model="s.size"
                                                    class="form-control input-sm"
                                                    placeholder="S, M, L..."
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    v-model="s.stock"
                                                    class="form-control input-sm"
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    v-model="s.price"
                                                    class="form-control input-sm"
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td>
                                                <button
                                                    class="btn-icon-danger square"
                                                    @click="
                                                        removeSize(
                                                            vIndex,
                                                            sIndex,
                                                        )
                                                    "
                                                >
                                                    <svg
                                                        width="14"
                                                        height="14"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <line
                                                            x1="18"
                                                            y1="6"
                                                            x2="6"
                                                            y2="18"
                                                        ></line>
                                                        <line
                                                            x1="6"
                                                            y1="6"
                                                            x2="18"
                                                            y2="18"
                                                        ></line>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <button
                                    class="btn-text-link mt-2"
                                    @click="addSize(vIndex)"
                                >
                                    + Thêm Kích Cỡ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings -->
            <div class="form-column side-col">
                <div
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.15s"
                >
                    <h3 class="card-title">Hình Ảnh Sản Phẩm</h3>
                    <div class="form-group mb-0">
                        <div class="image-upload-box">
                            <div
                                v-if="product.imagePreview"
                                class="preview-container"
                            >
                                <img
                                    :src="product.imagePreview"
                                    alt="Preview"
                                    class="img-preview"
                                />
                                <button
                                    class="remove-img-btn"
                                    @click.prevent="product.imagePreview = ''"
                                >
                                    ×
                                </button>
                            </div>
                            <div v-else class="upload-placeholder">
                                <svg
                                    width="32"
                                    height="32"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    opacity="0.5"
                                >
                                    <rect
                                        x="3"
                                        y="3"
                                        width="18"
                                        height="18"
                                        rx="2"
                                        ry="2"
                                    ></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline
                                        points="21 15 16 10 5 21"
                                    ></polyline>
                                </svg>
                                <span
                                    >Kéo thả ảnh hoặc Bấm tải Lên Ảnh Bìa
                                    Chính</span
                                >
                                <span class="upload-hint"
                                    >Khuyến nghị: Định dạng 800x800px</span
                                >
                                <input
                                    type="file"
                                    class="file-input-hide"
                                    accept="image/*"
                                    @change="handleThumbnailChange"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.25s"
                >
                    <h3 class="card-title">Thông Tin Phân Loại</h3>

                    <div class="form-group">
                        <label>Danh Mục</label>
                        <select
                            v-model="product.category_id"
                            class="form-control form-select"
                        >
                            <option value="">Chọn danh mục cho sản phẩm</option>
                            <AdminCategoryFormTree :categories="categories" />
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Thương Hiệu</label>
                        <select
                            v-model="product.brand_id"
                            class="form-control form-select"
                        >
                            <option value="">
                                Chọn thương hiệu cho sản phẩm
                            </option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nhà Cung Cấp</label>
                        <input
                            type="text"
                            v-model="product.seller_id"
                            class="form-control"
                            placeholder="Nhập mã nhà cung cấp"
                        />
                    </div>
                </div>

                <div
                    class="ocean-card form-card animate-in"
                    style="animation-delay: 0.35s"
                >
                    <h3 class="card-title">Trạng Thái Xét Duyệt</h3>

                    <div class="form-group">
                        <label>Trạng Thái</label>
                        <select
                            v-model="product.status"
                            class="form-control form-select"
                        >
                            <option value="draft">Bản Nháp (Đang ẩn)</option>
                            <option value="published">
                                Công Khai (Hiển thị ngay)
                            </option>
                            <option value="archived">
                                Lưu Trữ (Sản phẩm cũ)
                            </option>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="toggle-switch-wrapper">
                            <span class="toggle-label">
                                <strong>Sản Phẩm Nổi Bật</strong>
                                <span
                                    >Sản phẩm này sẽ hiện trên trang chủ mục
                                    tiêu biểu</span
                                >
                            </span>
                            <div class="toggle-switch">
                                <input
                                    type="checkbox"
                                    v-model="product.is_featured"
                                    class="toggle-input"
                                />
                                <span class="toggle-slider"></span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        </form>
    </div>
</template>

<style scoped>
.create-product-page {
    font-family: var(--font-inter);
    padding-bottom: 40px;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.back-link {
    margin-bottom: 8px;
}
.back-link a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}
.back-link a:hover {
    color: var(--ocean-blue);
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.2;
}
.page-subtitle {
    font-size: 0.9rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 4px;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Buttons */
.btn-primary {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    background: var(--ocean-blue);
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(2, 136, 209, 0.2);
    transition: all 0.2s;
}
.btn-primary:hover {
    background: var(--ocean-bright);
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(2, 136, 209, 0.3);
}

.btn-outline {
    padding: 10px 20px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: white;
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-outline:hover {
    background: var(--ocean-deepest);
    border-color: var(--text-light);
}

.btn-outline-small {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--ocean-blue);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-outline-small:hover {
    background: rgba(2, 136, 209, 0.05);
    border-color: var(--ocean-blue);
}

.btn-text-link {
    background: none;
    border: none;
    color: var(--ocean-blue);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}
.btn-text-link:hover {
    text-decoration: underline;
}

.btn-icon-danger {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: rgba(239, 83, 80, 0.1);
    color: var(--coral);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-icon-danger:hover {
    background: var(--coral);
    color: white;
}
.btn-icon-danger.square {
    width: 32px;
    height: 32px;
    border-radius: 6px;
}

/* Grid Layout */
.form-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
@media (max-width: 900px) {
    .form-container {
        grid-template-columns: 1fr;
    }
}
.form-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Cards */
.form-card {
    padding: 24px;
}
.card-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}
.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}
.card-header-flex .card-title {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

/* Forms */
.form-group {
    margin-bottom: 18px;
}
.form-group.mb-0 {
    margin-bottom: 0;
}
.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
}
.required {
    color: var(--coral);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: white;
    color: var(--text-main);
    font-family: var(--font-inter);
    font-size: 0.85rem;
    transition: all 0.2s;
}
.form-control:focus {
    border-color: var(--ocean-blue);
    outline: none;
    box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
}
.form-control::placeholder {
    color: var(--text-light);
}
.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
}
.input-sm {
    padding: 8px 10px;
    font-size: 0.8rem;
}

.price-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.input-with-prefix {
    display: flex;
    align-items: center;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
    background: white;
    transition: all 0.2s;
}
.input-with-prefix:focus-within {
    border-color: var(--ocean-blue);
    box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
}
.prefix {
    padding: 10px 14px;
    background: var(--ocean-deepest);
    color: var(--text-muted);
    font-weight: 600;
    border-right: 1px solid var(--border-color);
    font-size: 0.85rem;
}
.input-with-prefix .form-control {
    border: none;
    border-radius: 0;
    box-shadow: none !important;
}

/* Radio Type Selector */
.radio-group {
    display: flex;
    gap: 16px;
}
.radio-label {
    flex: 1;
    cursor: pointer;
}
.radio-label input[type="radio"] {
    display: none;
}
.radio-box {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 14px;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.radio-text {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-main);
    display: block;
}
.radio-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}
.radio-label input[type="radio"]:checked + .radio-box {
    border-color: var(--ocean-blue);
    background: rgba(2, 136, 209, 0.05);
    box-shadow: 0 0 0 1px var(--ocean-blue);
}

/* Image Upload */
.image-upload-box {
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    background: var(--ocean-deepest);
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}
.image-upload-box:hover {
    border-color: var(--ocean-blue);
    background: var(--hover-bg);
}
.upload-placeholder {
    padding: 40px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
}
.upload-hint {
    font-size: 0.7rem;
    font-weight: 500;
    opacity: 0.7;
}
.file-input-hide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.image-upload-box.small .upload-placeholder {
    padding: 20px 10px;
}

.preview-container {
    position: relative;
    width: 100%;
    padding-top: 100%; /* 1:1 Aspect Ratio */
}
.img-preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 10px;
}
.remove-img-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: white;
    border: 1px solid var(--border-color);
    color: var(--coral);
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

/* Variants */
.variant-item {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
    background: var(--ocean-deepest);
}
.variant-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: white;
    border-bottom: 1px solid var(--border-color);
}
.variant-header h4 {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-main);
}
.variant-body {
    padding: 16px;
}

.sizes-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}
.sizes-table th {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-align: left;
    padding: 0 8px 8px 0;
}
.sizes-table td {
    padding: 0 8px 8px 0;
}
.sizes-table td:last-child {
    padding-right: 0;
    width: 40px;
}

/* Toggle Switch */
.toggle-switch-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.toggle-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.toggle-label strong {
    font-size: 0.85rem;
    color: var(--text-main);
}
.toggle-label span {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}

.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}
.toggle-input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--text-light);
    transition: 0.3s;
    border-radius: 24px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}
.toggle-input:checked + .toggle-slider {
    background-color: var(--ocean-blue);
}
.toggle-input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

/* Utility */
.mt-2 {
    margin-top: 8px;
}
</style>