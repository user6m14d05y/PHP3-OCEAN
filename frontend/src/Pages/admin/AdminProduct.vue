<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../axios.js';

const products = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');

onMounted(() => {
    api.get('/productsAll?page=1&limit=10')
        .then(response => {
            products.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching products:', error);
        })
        .finally(() => {
            isLoading.value = false;
        });
});

const filteredProducts = computed(() => {
    if (!searchQuery.value) return products.value;
    const q = searchQuery.value.toLowerCase();
    return products.value.filter(p =>
        p.name?.toLowerCase().includes(q) ||
        String(p.product_id).includes(q)
    );
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};
</script>

<template>
    <div class="products-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ocean-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    Quản lý Sản phẩm
                </h1>
                <p class="page-subtitle">Quản lý kho hàng ocean store của bạn</p>
            </div>
            <router-link to="/admin/product/create" class="btn-primary" id="add-product-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Thêm Sản phẩm
            </router-link>
        </div>

        <!-- Filters & Search -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input 
                    type="text" 
                    v-model="searchQuery"
                    placeholder="Tìm kiếm sản phẩm theo tên hoặc ID..." 
                    class="search-input"
                />
            </div>
            <div class="filter-actions">
                <button class="filter-btn active">
                   <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Tất cả
                </button>
                <button class="filter-btn">Còn hàng</button>
                <button class="filter-btn">Sắp hết hàng</button>
            </div>
        </div>



        <!-- Products Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredProducts.length }}</strong> sản phẩm được tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TÊN SẢN PHẨM</th>
                            <th>GIÁ</th>
                            <th>KHO</th>
                            <th>HÀNH ĐỘNG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in filteredProducts" :key="p.product_id">
                            <td><span class="badge-id">#{{ p.product_id }}</span></td>
                            <td>
                                <div class="prod-cell">
                                    <div class="prod-thumb">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                                        </svg>
                                    </div>
                                    <span class="prod-name">{{ p.name }}</span>
                                </div>
                            </td>
                            <td><span class="val-price">{{ formatPrice(p.lowest_price_variant?.price) }}</span></td>
                            <td>
                                <span class="badge-stock" :class="{ 
                                    'good': (p.lowest_price_variant?.stock || 0) > 20, 
                                    'low': (p.lowest_price_variant?.stock || 0) <= 20 && (p.lowest_price_variant?.stock || 0) > 0, 
                                    'out': (p.lowest_price_variant?.stock || 0) === 0 
                                }">
                                    {{ p.lowest_price_variant?.stock || 0 }}
                                </span>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-icon view" title="Xem">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button class="btn-icon edit" title="Sửa">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="btn-icon del" title="Xóa">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-if="filteredProducts.length === 0" class="empty-state">
                <span class="empty-emoji">🐚</span>
                <h3>Không tìm thấy sản phẩm</h3>
                <p>Thử một từ khóa khác.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.products-page { font-family: var(--font-inter); }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500;}

/* Buttons */
.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; border: none;
    background: var(--ocean-blue); color: white;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(2, 136, 209, 0.2);
}
.btn-primary:hover {
    background: var(--ocean-bright); transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3);
}

/* Filters */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; margin-bottom: 24px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 350px;
    transition: all 0.2s;
}
.search-box:focus-within {
    border-color: var(--ocean-blue); background: white;
    box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
}
.search-box svg { color: var(--text-light); }
.search-input {
    background: none; border: none; outline: none;
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.9rem; width: 100%;
}
.search-input::placeholder { color: var(--text-light); }
.filter-actions { display: flex; gap: 8px; }
.filter-btn {
    padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    font-family: var(--font-inter); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; gap: 6px;
}
.filter-btn:hover { border-color: var(--ocean-blue); color: var(--ocean-blue); }
.filter-btn.active {
    background: rgba(2, 136, 209, 0.1); border-color: rgba(2, 136, 209, 0.3);
    color: var(--ocean-blue);
}

/* Loading */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600;}
.spinner {
    width: 30px; height: 30px; border: 3px solid var(--border-color);
    border-top-color: var(--ocean-blue); border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500;}
.table-count strong { color: var(--text-main); font-weight: 800;}
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color);
    background: var(--ocean-deepest);
}
.data-table td {
    padding: 16px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s;
}
.data-table tbody tr:hover td { background: var(--hover-bg); }

/* Badges */
.badge-id {
    padding: 4px 8px; border-radius: 6px; font-size: 0.8rem;
    font-weight: 700; background: rgba(2, 136, 209, 0.1); color: var(--ocean-blue);
}
.prod-cell { display: flex; align-items: center; gap: 12px; }
.prod-thumb {
    width: 40px; height: 40px; border-radius: 8px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-light);
}
.prod-name { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
.val-price { font-size: 0.9rem; font-weight: 800; color: var(--seafoam); }

.badge-stock {
    padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;
}
.good { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.low { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.out { background: rgba(239, 83, 80, 0.15); color: #c62828; }

/* Actions */
.actions-cell { display: flex; gap: 6px; }
.btn-icon {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.btn-icon:hover { border-color: currentColor; background: white;}
.view:hover { color: var(--ocean-blue); border-color: var(--ocean-blue); background: rgba(2, 136, 209, 0.05); }
.edit:hover { color: var(--seafoam); border-color: var(--seafoam); background: rgba(38, 166, 154, 0.05); }
.del:hover { color: var(--coral); border-color: var(--coral); background: rgba(239, 83, 80, 0.05); }

/* Empty */
.empty-state {
    text-align: center; padding: 60px 20px;
}
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500;}

/* Responsive */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    .filters-bar { flex-direction: column; gap: 12px; align-items: stretch; }
    .search-box { max-width: 100%; }
}
</style>