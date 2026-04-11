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
                </div>

                <!-- Main Image -->
                <img
                    :src="props.product.image"
                    :alt="props.product.name"
                    class="product-img main-img"
                    loading="lazy"
                />
                
                <!-- Quick Actions (Hover) -->
                <div class="product-hover-overlay">
                    <button class="overlay-btn overlay-btn-fav" 
                            :class="{'is-active': isFavorited(props.product.id || props.product.product_id)}" 
                            @click.prevent="handleToggleFav">
                        <span v-if="isFavorited(props.product.id || props.product.product_id)">ĐÃ LƯU</span>
                        <span v-else>YÊU THÍCH</span>
                    </button>
                    <button class="overlay-btn overlay-btn-cart">
                        XEM CHI TIẾT
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
    transition: all 0.4s ease;
    background: var(--card-bg, #ffffff);
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    overflow: hidden;
    height: 100%;
}

.product-card:hover {
    transform: none;
    box-shadow: none;
    border-color: transparent;
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

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.8s cubic-bezier(0.25, 1, 0.5, 1);
}

.product-card:hover .main-img {
    transform: scale(1.05);
}

.product-hover-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    display: flex;
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-top: 1px solid var(--border-color);
    z-index: 10;
}

.product-card:hover .product-hover-overlay {
    transform: translateY(0);
}

.overlay-btn {
    flex: 1;
    background: transparent;
    border: none;
    padding: 14px 0;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ocean-blue);
    text-align: center;
    cursor: pointer;
    letter-spacing: 0.5px;
    transition: all 0.2s;
}

.overlay-btn-fav {
    border-right: 1px solid var(--border-color);
}

.overlay-btn:hover {
    background: var(--ocean-blue);
    color: white;
}

.overlay-btn-fav.is-active {
    color: var(--text-muted);
}

.product-info {
    padding: 16px 0;
    background: white;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.product-name {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text-main);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: opacity 0.2s;
}

.product-card:hover .product-name {
    opacity: 0.7;
    color: var(--text-main);
}

.product-price {
    font-weight: 700;
    color: var(--text-main);
    font-size: 0.95rem;
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
