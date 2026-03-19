<script setup>
import { ref, reactive, onMounted, computed, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "@/axios";
import AdminCategoryFormTree from "@/components/AdminCategoryFormTree.vue";
import Quill from 'quill'
import 'quill/dist/quill.snow.css'

let quillShort = null;
let quillLong = null;
const editorShort = ref(null);
const editorLong = ref(null);

const initQuill = () => {
    const modules = {
        toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
        ],
    };

    if (editorShort.value && !quillShort) {
        quillShort = new Quill(editorShort.value, {
            theme: 'snow',
            placeholder: 'Nhập mô tả ngắn gọn...',
            modules
        });
        if (product.short_description) {
            quillShort.root.innerHTML = product.short_description;
        }
        quillShort.on('text-change', () => {
            product.short_description = quillShort.root.innerHTML === '<p><br></p>' ? '' : quillShort.root.innerHTML;
        });
    }

    if (editorLong.value && !quillLong) {
        quillLong = new Quill(editorLong.value, {
            theme: 'snow',
            placeholder: 'Nhập chi tiết sản phẩm...',
            modules
        });
        if (product.description) {
            quillLong.root.innerHTML = product.description;
        }
        quillLong.on('text-change', () => {
            product.description = quillLong.root.innerHTML === '<p><br></p>' ? '' : quillLong.root.innerHTML;
        });
    }
};

const router = useRouter();
const route = useRoute();
const productId = computed(() => route.params.id);

const categories = ref([]);
const brands = ref([]);
const errors = ref({});
const isLoading = ref(true);
const isSaving = ref(false);

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
    product_type: "simple",
    status: "draft",
    is_featured: false,
    price: "",
    stock: "",
    variants: [],
    gallery_files: [],
    galleryPreviews: [],
    existing_gallery: [],
    deleted_gallery_ids: [],
});

const storageUrl = import.meta.env.VITE_API_STORAGE || "http://localhost:8383/storage";

const handleFetchCategories = async () => {
    try {
        const response = await api.get("/categories");
        categories.value = response.data;
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

const fetchProduct = async () => {
    try {
        const response = await api.get(`/products/${productId.value}/edit`);
        const p = response.data;
        product.category_id = p.category_id || "";
        product.brand_id = p.brand_id || "";
        product.seller_id = p.seller_id || "";
        product.name = p.name || "";
        product.slug = p.slug || "";
        product.short_description = p.short_description || "";
        product.description = p.description || "";
        product.product_type = p.product_type || "simple";
        product.status = p.status || "draft";
        product.is_featured = p.is_featured || false;
        product.thumbnail_url = p.thumbnail_url || "";
        product.imagePreview = p.thumbnail_url ? `${storageUrl}/${p.thumbnail_url}` : "";

        // Xử lý biến thể - thêm imagePreview + new_images cho mỗi biến thể
        product.variants = (p.variants || []).map(v => ({
            ...v,
            imagePreview: v.image_url ? `${storageUrl}/${v.image_url}` : "",
            new_image: null,
        }));

        // Gallery images (loại bỏ ảnh chính, ảnh biến thể)
        if (p.images && p.images.length > 0) {
            product.existing_gallery = p.images.filter(img => !img.is_main && !img.variant_id);
        }

        // For simple product, get price/stock from first variant
        if (p.product_type === "simple" && p.variants && p.variants.length > 0) {
            product.price = p.variants[0].price || "";
            product.stock = p.variants[0].stock || "";
        } else {
            product.price = p.min_price || "";
            product.stock = "";
        }
    } catch (error) {
        console.error("Error fetching product:", error);
        alert("Không thể tải thông tin sản phẩm.");
    } finally {
        isLoading.value = false;
        nextTick(() => {
            initQuill();
        });
    }
};

const generateSlug = () => {
    if (product.name) {
        product.slug = product.name
            .toLowerCase()
            .replace(/\s+/g, "-")
            .replace(/[^a-z0-9-]/g, "");
    }
};

// ===== Xử lý ảnh =====
const handleThumbnailChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        product.thumbnail_url = file;
        product.imagePreview = URL.createObjectURL(file);
    }
};

const handleGalleryChange = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        product.gallery_files.push(file);
        product.galleryPreviews.push(URL.createObjectURL(file));
    });
    event.target.value = '';
};

const removeNewGalleryImage = (index) => {
    product.gallery_files.splice(index, 1);
    product.galleryPreviews.splice(index, 1);
};

const removeExistingGalleryImage = (index, imageId) => {
    product.existing_gallery.splice(index, 1);
    product.deleted_gallery_ids.push(imageId);
};

const handleVariantImageChange = (event, index) => {
    const file = event.target.files[0];
    if (file) {
        product.variants[index].new_image = file;
        product.variants[index].imagePreview = URL.createObjectURL(file);
    }
};

const removeVariantImage = (index) => {
    product.variants[index].imagePreview = "";
    product.variants[index].new_image = null;
    product.variants[index].image_url = null;
    product.variants[index].remove_image = true;
};

// ===== Form validation =====
const validateForm = () => {
    errors.value = {};
    let isValid = true;

    if (!product.name) {
        errors.value.name = "Tên sản phẩm là bắt buộc";
        isValid = false;
    }
    if (!product.slug) {
        errors.value.slug = "Slug là bắt buộc";
        isValid = false;
    }
    if (!product.category_id) {
        errors.value.category_id = "Danh mục là bắt buộc";
        isValid = false;
    }

    if (product.product_type === "simple") {
        if (!product.price) {
            errors.value.price = "Giá là bắt buộc";
            isValid = false;
        }
        if (product.stock === "" || product.stock === null) {
            errors.value.stock = "Số lượng kho là bắt buộc";
            isValid = false;
        }
    }

    return isValid;
};

// ===== Submit =====
const handleSubmit = async () => {
    if (!validateForm()) {
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    isSaving.value = true;
    const formData = new FormData();
    formData.append("_method", "PUT");
    formData.append("name", product.name);
    formData.append("slug", product.slug);
    formData.append("category_id", product.category_id);
    formData.append("brand_id", product.brand_id || "");
    formData.append("seller_id", product.seller_id || "");
    formData.append("short_description", product.short_description || "");
    formData.append("description", product.description || "");
    formData.append("product_type", product.product_type);
    formData.append("status", product.status);
    formData.append("is_featured", product.is_featured ? "1" : "0");

    if (product.thumbnail_url instanceof File) {
        formData.append("thumbnail", product.thumbnail_url);
    }

    // Gallery mới
    product.gallery_files.forEach((file, index) => {
        if (file instanceof File) {
            formData.append(`gallery[${index}]`, file);
        }
    });

    // Gallery đã xóa
    product.deleted_gallery_ids.forEach((id, index) => {
        formData.append(`deleted_gallery_ids[${index}]`, id);
    });

    if (product.product_type === "simple") {
        formData.append("price", product.price);
        formData.append("stock", product.stock);
    } else if (product.product_type === "variant" && product.variants.length > 0) {
        product.variants.forEach((v, index) => {
            formData.append(`variants[${index}][variant_id]`, v.variant_id);
            formData.append(`variants[${index}][price]`, v.price);
            formData.append(`variants[${index}][stock]`, v.stock);
            formData.append(`variants[${index}][status]`, v.status);
            if (v.new_image instanceof File) {
                formData.append(`variants[${index}][image]`, v.new_image);
            }
            if (v.remove_image) {
                formData.append(`variants[${index}][remove_image]`, '1');
            }
        });
    }

    try {
        await api.post(`/products/${productId.value}`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        alert("Cập nhật sản phẩm thành công!");
        router.push("/admin/product");
    } catch (error) {
        console.error("Error:", error.response?.data || error);
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            alert(error.response?.data?.message || "Có lỗi xảy ra khi cập nhật.");
        }
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    handleFetchCategories();
    handleFetchBrands();
    fetchProduct();
});
</script>

<template>
    <div class="edit-product-page">
        <!-- Loading -->
        <div v-if="isLoading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang tải thông tin sản phẩm...</p>
        </div>

        <form v-else @submit.prevent="handleSubmit" enctype="multipart/form-data">
            <!-- Header -->
            <div class="page-header animate-in">
                <div class="header-info">
                    <div class="back-link">
                        <router-link to="/admin/product">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Trở Về
                        </router-link>
                    </div>
                    <h1 class="page-title">Chỉnh Sửa Sản Phẩm</h1>
                    <p class="page-subtitle">Cập nhật thông tin sản phẩm #{{ productId }}</p>
                </div>
                <div class="header-actions">
                    <router-link to="/admin/product" class="btn-outline">Hủy bỏ</router-link>
                    <button type="submit" class="btn-primary" :disabled="isSaving">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        {{ isSaving ? 'Đang lưu...' : 'Lưu Thay Đổi' }}
                    </button>
                </div>
            </div>

            <div class="form-container">
                <!-- Left Column -->
                <div class="form-column main-col">
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.1s">
                        <h3 class="card-title">Thông Tin Cơ Bản</h3>

                        <div class="form-group">
                            <label>Tên Sản Phẩm <span class="required">*</span></label>
                            <input type="text" v-model="product.name" @input="generateSlug" class="form-control" placeholder="Ví dụ: Đồng Hồ Xanh Đại Dương" />
                            <div v-if="errors.name" class="error-message">{{ errors.name }}</div>
                        </div>

                        <div class="form-group">
                            <label>Đường Dẫn (Slug) <span class="required">*</span></label>
                            <input type="text" v-model="product.slug" class="form-control" placeholder="dong-ho-xanh-dai-duong" />
                            <div v-if="errors.slug" class="error-message">{{ errors.slug }}</div>
                        </div>

                        <div class="form-group">
                            <label>Mô Tả Ngắn</label>
                            <div class="quill-wrapper editor-short">
                                <div ref="editorShort"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Mô Tả Chi Tiết</label>
                            <div class="quill-wrapper editor-long">
                                <div ref="editorLong"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.2s">
                        <h3 class="card-title">Giá và Kho Hàng</h3>
                        <div class="info-badge mb-3">
                            Loại sản phẩm: <strong>{{ product.product_type === 'simple' ? 'Đơn giản' : 'Biến thể' }}</strong>
                        </div>

                        <div class="price-grid" v-if="product.product_type === 'simple'">
                            <div class="form-group">
                                <label>Giá Bán <span class="required">*</span></label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₫</span>
                                    <input type="number" v-model="product.price" class="form-control" placeholder="0" />
                                </div>
                                <div v-if="errors.price" class="error-message">{{ errors.price }}</div>
                            </div>
                            <div class="form-group">
                                <label>Số Lượng Kho <span class="required">*</span></label>
                                <input type="number" v-model="product.stock" class="form-control" placeholder="0" />
                                <div v-if="errors.stock" class="error-message">{{ errors.stock }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Biến thể (hiển thị dạng card giống trang thêm sản phẩm) -->
                    <div v-if="product.product_type === 'variant'" class="ocean-card form-card animate-in" style="animation-delay: 0.3s">
                        <h3 class="card-title">Biến Thể Sản Phẩm ({{ product.variants.length }})</h3>

                        <div v-if="product.variants.length === 0" class="empty-variant-msg">
                            <p>Chưa có biến thể nào.</p>
                        </div>

                        <div class="variant-item" v-for="(v, vIndex) in product.variants" :key="v.variant_id">
                            <div class="variant-header">
                                <h4>
                                    <span class="variant-color-dot" :style="v.color ? {background: '#0288d1'} : {}"></span>
                                    {{ v.color || 'Không tên' }} - {{ v.size || 'N/A' }}
                                </h4>
                                <code class="variant-sku">{{ v.sku }}</code>
                            </div>
                            <div class="variant-body">
                                <div class="variant-body-grid">
                                    <!-- Ảnh biến thể -->
                                    <div class="form-group variant-img-group">
                                        <label>Hình Ảnh Biến Thể</label>
                                        <div class="image-upload-box small">
                                            <div v-if="v.imagePreview" class="preview-container">
                                                <img :src="v.imagePreview" alt="Variant Preview" class="img-preview" />
                                                <button class="remove-img-btn" @click.prevent="removeVariantImage(vIndex)">×</button>
                                            </div>
                                            <div v-else class="upload-placeholder">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <polyline points="21 15 16 10 5 21"></polyline>
                                                </svg>
                                                <span>Tải ảnh lên</span>
                                                <input type="file" class="file-input-hide" accept="image/*" @change="(e) => handleVariantImageChange(e, vIndex)" />
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Giá / Kho / Trạng thái -->
                                    <div class="variant-fields">
                                        <div class="form-group">
                                            <label>Giá Bán (₫)</label>
                                            <div class="input-with-prefix">
                                                <span class="prefix">₫</span>
                                                <input type="number" v-model="v.price" class="form-control" placeholder="0" />
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Số Lượng Kho</label>
                                            <input type="number" v-model="v.stock" class="form-control" placeholder="0" />
                                        </div>
                                        <div class="form-group">
                                            <label>Trạng Thái</label>
                                            <select v-model="v.status" class="form-control form-select">
                                                <option value="active">Đang bán</option>
                                                <option value="inactive">Tạm ẩn</option>
                                                <option value="out_of_stock">Hết hàng</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="form-column side-col">
                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.15s">
                        <h3 class="card-title">Hình Ảnh Sản Phẩm</h3>
                        <div class="form-group">
                            <label>Ảnh Bìa Chính</label>
                            <div class="image-upload-box">
                                <div v-if="product.imagePreview" class="preview-container">
                                    <img :src="product.imagePreview" alt="Preview" class="img-preview" />
                                    <button class="remove-img-btn" @click.prevent="product.imagePreview = ''; product.thumbnail_url = ''">×</button>
                                </div>
                                <div v-else class="upload-placeholder">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span>Bấm để tải ảnh mới lên</span>
                                    <span class="upload-hint">Khuyến nghị: 800x800px</span>
                                    <input type="file" class="file-input-hide" accept="image/*" @change="handleThumbnailChange" />
                                </div>
                            </div>
                        </div>

                        <!-- Gallery Images -->
                        <div class="form-group mb-0">
                            <label>Ảnh Phụ (Nhiều ảnh)</label>
                            <div class="gallery-upload-container">
                                <!-- Ảnh gallery đã có -->
                                <div v-for="(img, index) in product.existing_gallery" :key="'ex-'+index" class="gallery-item">
                                    <img :src="`${storageUrl}/${img.image_url}`" alt="Existing Gallery" />
                                    <button class="remove-img-btn" @click.prevent="removeExistingGalleryImage(index, img.image_id)">×</button>
                                </div>
                                <!-- Ảnh gallery mới upload -->
                                <div v-for="(preview, index) in product.galleryPreviews" :key="'new-'+index" class="gallery-item">
                                    <img :src="preview" alt="New Gallery Preview" />
                                    <button class="remove-img-btn" @click.prevent="removeNewGalleryImage(index)">×</button>
                                </div>
                                <!-- Nút thêm ảnh -->
                                <div class="gallery-add-btn">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <input type="file" class="file-input-hide" accept="image/*" multiple @change="handleGalleryChange" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.25s">
                        <h3 class="card-title">Thông Tin Phân Loại</h3>

                        <div class="form-group">
                            <label>Danh Mục <span class="required">*</span></label>
                            <select v-model="product.category_id" class="form-control form-select">
                                <option value="">Chọn danh mục</option>
                                <AdminCategoryFormTree :categories="categories" />
                            </select>
                            <div v-if="errors.category_id" class="error-message">{{ errors.category_id }}</div>
                        </div>

                        <div class="form-group">
                            <label>Thương Hiệu</label>
                            <select v-model="product.brand_id" class="form-control form-select">
                                <option value="">Chọn thương hiệu</option>
                                <option v-for="b in brands" :key="b.brand_id" :value="b.brand_id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="ocean-card form-card animate-in" style="animation-delay: 0.35s">
                        <h3 class="card-title">Trạng Thái</h3>

                        <div class="form-group">
                            <label>Trạng Thái</label>
                            <select v-model="product.status" class="form-control form-select">
                                <option value="draft">Bản Nháp</option>
                                <option value="active">Đang Bán</option>
                                <option value="inactive">Tạm Ẩn</option>
                                <option value="out_of_stock">Hết Hàng</option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label class="toggle-switch-wrapper">
                                <span class="toggle-label">
                                    <strong>Sản Phẩm Nổi Bật</strong>
                                    <span>Hiển thị trên trang chủ</span>
                                </span>
                                <div class="toggle-switch">
                                    <input type="checkbox" v-model="product.is_featured" class="toggle-input" />
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
.edit-product-page {
    font-family: var(--font-inter);
    padding-bottom: 40px;
}

/* Loading */
.loading-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; }
.spinner {
    width: 30px; height: 30px; border: 3px solid var(--border-color);
    border-top-color: var(--ocean-blue); border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Header */
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.back-link { margin-bottom: 8px; }
.back-link a { display: flex; align-items: center; gap: 6px; color: var(--text-muted); font-weight: 600; font-size: 0.85rem; text-decoration: none; transition: color 0.2s; }
.back-link a:hover { color: var(--ocean-blue); }
.page-title { font-size: 1.45rem; font-weight: 800; color: var(--text-main); }
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }
.header-actions { display: flex; gap: 12px; align-items: center; }
.btn-primary { display: flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 8px; border: none; background: var(--ocean-blue); color: white; font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none; box-shadow: 0 4px 10px rgba(2,136,209,0.2); }
.btn-primary:hover:not(:disabled) { background: var(--ocean-bright); transform: translateY(-2px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-outline { padding: 10px 22px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-muted); font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none; }
.btn-outline:hover { border-color: var(--ocean-blue); color: var(--ocean-blue); }

/* Form Layout */
.form-container { display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
.ocean-card { background: white; border-radius: 12px; border: 1px solid var(--border-color); }
.form-card { padding: 24px; margin-bottom: 0; }
.card-title { font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color); }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
.required { color: var(--coral, #ef5350); }
.error-message { color: #ef5350; font-size: 0.8rem; margin-top: 4px; font-weight: 600; }
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--ocean-deepest, #f8fafc); font-family: var(--font-inter); font-size: 0.9rem; color: var(--text-main); transition: border-color 0.2s,box-shadow 0.2s; outline: none; box-sizing: border-box; }
.form-control:focus { border-color: var(--ocean-blue); box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
.form-select { appearance: auto; }

/* Price Grid */
.price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.input-with-prefix { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; transition: border-color 0.2s; }
.input-with-prefix:focus-within { border-color: var(--ocean-blue); box-shadow: 0 0 0 3px rgba(2,136,209,0.1); }
.input-with-prefix .prefix { padding: 10px 12px; background: var(--ocean-deepest); color: var(--text-muted); font-weight: 700; font-size: 0.85rem; border-right: 1px solid var(--border-color); }
.input-with-prefix .form-control { border: none; box-shadow: none; }

/* Info */
.info-badge { padding: 10px 14px; background: rgba(2,136,209,0.06); border-radius: 8px; font-size: 0.85rem; color: var(--text-main); }
.mb-3 { margin-bottom: 16px; }
.mb-0 { margin-bottom: 0 !important; }
.text-muted { color: var(--text-muted); }

/* Variant Cards */
.variant-item {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
    background: var(--ocean-deepest, #f8fafc);
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
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.variant-color-dot {
    width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; display: inline-block; flex-shrink: 0;
}
.variant-sku {
    font-size: 0.75rem; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; color: var(--text-muted); font-weight: 600;
}
.variant-body { padding: 16px; }
.variant-body-grid {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 16px;
    align-items: start;
}
.variant-img-group { margin-bottom: 0; }
.variant-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.variant-fields .form-group:last-child {
    grid-column: span 2;
}
.empty-variant-msg {
    padding: 20px;
    text-align: center;
    color: var(--text-muted);
    font-weight: 600;
}

/* Image Upload */
.image-upload-box { border: 2px dashed var(--border-color); border-radius: 12px; overflow: hidden; min-height: 180px; display: flex; align-items: center; justify-content: center; transition: border-color 0.2s; position: relative; }
.image-upload-box:hover { border-color: var(--ocean-blue); }
.image-upload-box.small { min-height: 120px; }
.image-upload-box.small .upload-placeholder { padding: 16px 10px; }
.preview-container { position: relative; width: 100%; }
.img-preview { width: 100%; height: 200px; object-fit: contain; display: block; padding: 8px; }
.image-upload-box.small .img-preview { height: 100px; }
.remove-img-btn { position: absolute; top: 8px; right: 8px; width: 24px; height: 24px; border-radius: 50%; background: rgba(239,83,80,0.9); color: white; border: none; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; padding: 0; line-height: 1; }
.remove-img-btn:hover { transform: scale(1.1); }
.upload-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 30px 20px; cursor: pointer; color: var(--text-muted); position: relative; width: 100%; }
.upload-placeholder span { font-size: 0.85rem; font-weight: 600; }
.upload-hint { font-size: 0.78rem !important; color: var(--text-light); }
.file-input-hide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

/* Gallery Upload */
.gallery-upload-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
.gallery-item { width: 72px; height: 72px; position: relative; border-radius: 8px; overflow: hidden; }
.gallery-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); }
.gallery-item .remove-img-btn { position: absolute; top: -4px; right: -4px; background: #ef5350; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; z-index: 10; padding: 0; }
.gallery-add-btn { width: 72px; height: 72px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; background: var(--ocean-deepest, #f8fafc); transition: border-color 0.2s; }
.gallery-add-btn:hover { border-color: var(--ocean-blue); }

/* Toggle */
.toggle-switch-wrapper { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.toggle-label { display: flex; flex-direction: column; gap: 2px; }
.toggle-label strong { font-size: 0.85rem; }
.toggle-label span { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; }
.toggle-switch { position: relative; width: 44px; height: 24px; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 24px; transition: 0.3s; cursor: pointer; }
.toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
.toggle-input:checked + .toggle-slider { background: var(--ocean-blue); }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }

/* Animation */
.animate-in { animation: fadeSlideUp 0.4s ease both; }
@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

/* Quill Custom Styles */
.quill-wrapper {
    display: flex;
    flex-direction: column;
}
.quill-wrapper :deep(.ql-toolbar.ql-snow) {
    border: 1px solid var(--border-color);
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: var(--ocean-deepest, #f8fafc);
    font-family: var(--font-inter);
    transition: border-color 0.2s;
}
.quill-wrapper :deep(.ql-container.ql-snow) {
    border: 1px solid var(--border-color);
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    border-top: none;
    font-family: var(--font-inter);
    font-size: 0.9rem;
    background: white;
    transition: border-color 0.2s;
}
.quill-wrapper:focus-within :deep(.ql-toolbar.ql-snow) {
    border-color: var(--ocean-blue);
}
.quill-wrapper:focus-within :deep(.ql-container.ql-snow) {
    border-color: var(--ocean-blue);
}
.quill-wrapper :deep(.ql-editor) {
    color: var(--text-main);
}
.editor-short :deep(.ql-editor) {
    min-height: 100px;
    max-height: 250px;
}
.editor-long :deep(.ql-editor) {
    min-height: 250px;
}

/* Responsive */
@media (max-width: 768px) {
    .form-container { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
    .price-grid { grid-template-columns: 1fr; }
    .variant-body-grid { grid-template-columns: 1fr; }
    .variant-fields { grid-template-columns: 1fr; }
    .variant-fields .form-group:last-child { grid-column: span 1; }
}
</style>
