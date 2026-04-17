<script setup>
import { computed } from "vue";
import { useFavorites } from '@/composables/useFavorites';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['unfavorite']);
const { isFavorited, toggleFavorite } = useFavorites();

const handleToggleFav = async () => {
    const pId = props.product.id || props.product.product_id;
    if (!pId) return;
    
    // Check old state
    const wasFavorited = isFavorited(pId);
    
    const success = await toggleFavorite(pId);
    if (success && wasFavorited) {
        emit('unfavorite', pId); // Notify parent if it was removed
    }
};

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
                    <span class="product-badge badge-sale" v-if="props.product.discount_percent > 0">
                        -{{ props.product.discount_percent }}%
                    </span>
                </div>

                <!-- Main Image -->
                <img
                    :src="props.product.image"
                    :alt="props.product.name"
                    class="product-img main-img"
                    loading="lazy"
                />
                
                <!-- Top Right Actions -->
                <div class="action-cluster">
                    <button class="icon-action-btn cart-icon-btn" @click.prevent="() => {}" title="Thêm vào giỏ">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/><path d="M20 20a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </button>
                    <button class="icon-action-btn fav-icon-btn" 
                            :class="{'is-active': isFavorited(props.product.id || props.product.product_id)}" 
                            @click.prevent="handleToggleFav" title="Yêu thích">
                        <svg v-if="isFavorited(props.product.id || props.product.product_id)" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                    </button>
                </div>
            </div>
            
            <div class="product-info">
                <h3 class="product-name text-truncate" :title="props.product.name">{{ props.product.name }}</h3>
                <div class="d-flex align-items-center mt-2" style="gap: 8px;">
                    <span class="product-price" :class="{'sale-price': props.product.originalPrice}">{{ props.product.price }}</span>
                    <span class="original-price" v-if="props.product.originalPrice">{{ props.product.originalPrice }}</span>
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
    transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    background: var(--card-bg, #ffffff);
    border: 1px solid #ebebeb;
    border-radius: 12px;
    overflow: hidden;
    height: 100%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: rgba(2, 136, 209, 0.15);
}

.product-img-wrapper {
    position: relative;
    width: 100%;
    margin: 0;
    border-radius: 10px;
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
    background: var(--ocean-blue);
    color: white;
    padding: 6px 14px;
    border-radius: var(--radius-micro);
    font-size: 0.70rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: none;
}

.badge-hot { background: #0F172A; }
.badge-new { background: #64748b; }
.badge-sale { background: var(--coral, #ff4757); }

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}

.product-card:hover .main-img {
    transform: scale(1.05); /* Slight gentle zoom */
}

/* Action cluster top right */
.action-cluster {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 10;
}

.icon-action-btn {
    background: #ffffff;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    opacity: 0;
    transform: translateX(10px);
}

/* Stagger animation */
.cart-icon-btn { transition-delay: 0.05s; }
.fav-icon-btn { transition-delay: 0.1s; }

.product-card:hover .icon-action-btn, 
.fav-icon-btn.is-active {
    opacity: 1;
    transform: translateX(0);
}

.icon-action-btn:hover {
    color: var(--ocean-blue, #0288D1);
    transform: translateX(0) scale(1.08) !important;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
}

.fav-icon-btn.is-active {
    color: #ff4757;
}

.fav-icon-btn.is-active:hover {
    background: #ffffff;
    color: #ff6b81;
}

.product-info {
    padding: 12px 16px 20px;
    background: transparent;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.product-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text-main);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: opacity 0.2s;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 44px; /* ~2 lines */
    line-height: 1.4;
}

.product-card:hover .product-name {
    opacity: 0.7;
    color: var(--text-main);
}

.product-price {
    font-weight: 800;
    color: var(--ocean-blue);
    font-size: 1.05rem;
}

.product-price.sale-price {
    color: var(--coral, #ff4757);
}

.original-price {
    font-size: 0.85rem;
    color: #94a3b8;
    text-decoration: line-through;
    font-weight: 500;
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
