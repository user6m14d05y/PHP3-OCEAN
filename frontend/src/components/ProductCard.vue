<script setup>
import { computed } from "vue";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

</script>

<template>
    <div class="product-card ocean-card group">
        <router-link
            :to="{
                name: 'product-detail',
                params: { slug: props.product.slug },
            }"
            class="text-decoration-none"
        >
            <div class="product-img-wrapper">
                <!-- Badges -->
                <div class="badges-container">
                    <span
                        class="product-badge"
                        v-if="props.product.badge"
                        :class="{'badge-hot': props.product.badge === 'Hot', 'badge-new': props.product.badge === 'New'}"
                    >
                        {{ props.product.badge }}
                    </span>
                </div>

                <!-- Main Image -->
                <img
                    :src="props.product.image"
                    :alt="props.product.name"
                    class="product-img main-img"
                    loading="lazy"
                />
                
                <!-- Quick Actions (Hover) -->
                <div class="product-hover-action d-flex align-items-center gap-3">
                    <button class="btn-icon btn-cart" title="Xem chi tiết">
                        <i class="fas fa-shopping-bag"></i>
                    </button>
                </div>
            </div>
            
            <div class="product-info">
                <h3 class="product-name text-truncate" :title="props.product.name">{{ props.product.name }}</h3>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="product-price">{{ props.product.price }}</span>
                </div>
            </div>
        </router-link>
    </div>
</template>

<style scoped>
.product-card {
    padding: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #d9e8f0);
    border-radius: 16px;
    overflow: hidden;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 32px rgba(2, 136, 209, 0.12);
    border-color: rgba(2, 136, 209, 0.4);
}

.product-img-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 3/4; /* Chuẩn tỉ lệ ảnh thời trang */
    background: #f8fafc;
    overflow: hidden;
}

.badges-container {
    position: absolute;
    top: 16px;
    left: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 10;
}

.product-badge {
    background: var(--seafoam, #26a69a);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.badge-hot { background: #ef4444; }
.badge-new { background: #10b981; }

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.8s cubic-bezier(0.25, 1, 0.5, 1);
}

.product-card:hover .main-img {
    transform: scale(1.1);
}

.product-hover-action {
    position: absolute;
    bottom: -60px;
    left: 50%;
    transform: translateX(-50%);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 10;
    opacity: 0;
}

.product-card:hover .product-hover-action {
    bottom: 24px;
    opacity: 1;
}

.btn-icon {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    border: none;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-main);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1.2rem;
}

.btn-cart:hover {
    background: var(--ocean-blue, #0288d1);
    color: white;
    transform: translateY(-4px) scale(1.05);
}

.product-info {
    padding: 20px 24px;
    background: white;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.product-name {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-main, #102a43);
    transition: color 0.2s;
}

.product-card:hover .product-name {
    color: var(--ocean-blue, #0288d1);
}

.product-price {
    font-weight: 800;
    color: var(--coral, #ef5350);
    font-size: 1.2rem;
}

.swatch-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px #cbd5e1;
    cursor: pointer;
    transition: transform 0.2s;
}

.swatch-dot:hover {
    transform: scale(1.3);
}
</style>
